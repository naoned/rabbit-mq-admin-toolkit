<?php

namespace Bab\RabbitMq;

use Bab\RabbitMq\Amqp\QueueConfiguration;
use Bab\RabbitMq\Specification\DeadLetterExchangeCanBeCreated;
use Bab\RabbitMq\Specification\DelayExchangeCanBeCreated;
use Bab\RabbitMq\Specification\RetryExchangeCanBeCreated;
use InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

final class VhostManager
{
    use LoggerAwareTrait;

    private array
        $credentials;
    private Action
        $action;
    private ?Configuration
        $mappingConfiguration;

    public function __construct(array $credentials, Action $action)
    {
        $this->credentials = $credentials;
        $this->credentials['vhost'] = str_replace('/', '%2f', $this->credentials['vhost']);

        $this->action = $action;
        $this->action->setVhost($this->credentials['vhost']);

        $this->logger = new NullLogger();
        $this->mappingConfiguration = null;
    }

    public function resetVhost(): void
    {
        $this->action->deleteVhost();
        $this->action->createVhost($this->credentials);
    }

    public function createMapping(Configuration $config): void
    {
        $this->mappingConfiguration = $config;

        $this->createBaseStructure($config);
        $this->createExchanges($config);
        $this->createQueues($config);
        $this->setPermissions($config);

        $this->mappingConfiguration = null;
    }

    private function createExchange(string $exchange, array $parameters = []): void
    {
        $this->action->createExchange($exchange, $parameters);
    }

    private function createQueue(string $queue, array $parameters = []): void
    {
        if($this->mappingConfiguration && $this->mappingConfiguration->hasQueueTypeBeenDefined())
        {
            $parameters['arguments']['x-queue-type'] = $this->mappingConfiguration->queueType();
        }

        $this->action->createQueue($queue, $parameters);
    }

    private function createBinding(string $exchange, string $queue, ?string $routingKey = null, array $arguments = []): void
    {
        $this->action->createBinding($exchange, $queue, $routingKey, $arguments);
    }

    private function createUnroutable(): void
    {
        $this->createExchange('unroutable', [
            'type' => 'fanout',
            'durable' => true,
        ]);
        $this->createQueue('unroutable', [
            'auto_delete' => 'false',
            'durable' => true,
        ]);
        $this->createBinding('unroutable', 'unroutable');
    }

    private function createDlExchange(): void
    {
        $this->createExchange('dl', [
            'type' => 'direct',
            'durable' => true,
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    private function createRetryExchange(): void
    {
        $this->createExchange('retry', [
            'durable' => true,
            'type' => 'topic',
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    private function createDelayExchange(): void
    {
        $this->createExchange('delay', [
            'durable' => true,
        ]);
    }

    private function setPermissions(Configuration $config): void
    {
        if(! empty($config['permissions']))
        {
            foreach($config['permissions'] as $user => $userPermissions)
            {
                $parameters = $this->extractPermissions($userPermissions);
                $this->action->setPermissions($user, $parameters);
            }
        }
    }

    private function extractPermissions(array $userPermissions = []): array
    {
        $permissions = [
            'configure' => '',
            'read' => '',
            'write' => '',
        ];

        if(! empty($userPermissions))
        {
            foreach(array_keys($permissions) as $permission)
            {
                if(! empty($userPermissions[$permission]))
                {
                    $permissions[$permission] = $userPermissions[$permission];
                }
            }
        }

        return $permissions;
    }

    private function log(string $message): void
    {
        $this->logger->info($message);
    }

    private function createBaseStructure(Configuration $config): void
    {
        $this->log(sprintf('With DL: <info>%s</info>', true === $config->hasDeadLetterExchange() ? 'true' : 'false'));
        $this->log(sprintf('With Unroutable: <info>%s</info>', true === $config->hasUnroutableExchange() ? 'true' : 'false'));

        // Unroutable queue must be created even if not asked but with_dl is
        // true to not lose unroutable messages which enters in dl exchange
        if(true === $config->hasDeadLetterExchange() || true === $config->hasUnroutableExchange())
        {
            $this->createUnroutable();
        }

        if((new DeadLetterExchangeCanBeCreated())->isSatisfiedBy($config))
        {
            $this->createDlExchange();
        }

        if((new RetryExchangeCanBeCreated())->isSatisfiedBy($config))
        {
            $this->createRetryExchange();
        }

        if((new DelayExchangeCanBeCreated())->isSatisfiedBy($config))
        {
            $this->createDelayExchange();
        }
    }

    private function createExchanges(Configuration $config): void
    {
        foreach($config['exchanges'] as $name => $parameters)
        {
            $currentWithUnroutable = $config->hasUnroutableExchange();

            if(isset($parameters['with_unroutable']))
            {
                $currentWithUnroutable = (bool)$parameters['with_unroutable'];
                unset($parameters['with_unroutable']);
            }

            if($currentWithUnroutable && ! isset($config['arguments']['alternate-exchange']))
            {
                if(! isset($parameters['arguments']))
                {
                    $parameters['arguments'] = [];
                }
                $parameters['arguments']['alternate-exchange'] = 'unroutable';
            }

            $this->createExchange($name, $parameters);
        }
    }

    private function createQueues(Configuration $config): void
    {
        if(! isset($config['queues']) || 0 === count($config['queues']))
        {
            return;
        }

        foreach($config['queues'] as $name => $parameters)
        {
            $this->createOneQueue($config, $name, $parameters);
        }
    }

    private function createOneQueue(Configuration $config, string $name, array $parameters): void
    {
        $queueConfig = new QueueConfiguration($name, $parameters, $config);

        $this->createQueue($queueConfig->name(), $queueConfig->parameters());

        if($queueConfig->hasDelay())
        {
            $this->createDelayArtifacts($queueConfig);
        }

        if($queueConfig->withDL())
        {
            $this->createDeadLetterQueueAndBindings($queueConfig->name());
        }

        $this->createRetryQueues($queueConfig);

        foreach($queueConfig->bindings() as $binding)
        {
            $this->createUserBinding($name, $binding, $queueConfig->delay());
        }
    }

    private function createUserBinding(string $queueName, array $bindingDefinition, ?int $delay = null): void
    {
        $defaultParameterValues = [
            'routing_key' => null,
            'x-match' => 'all',
            'matches' => [],
        ];

        $parameters = array_merge($defaultParameterValues, $bindingDefinition);

        if(! isset($parameters['exchange']))
        {
            throw new InvalidArgumentException(sprintf('Exchange is missing in binding for queue %s', $queueName));
        }

        $arguments = [];
        if(! empty($parameters['matches']))
        {
            $arguments = $parameters['matches'];
            $arguments['x-match'] = $parameters['x-match'];
        }

        $bindingName = null !== $delay ? $queueName . '_delay_' . $delay : $queueName;

        $this->createBinding($parameters['exchange'], $bindingName, $parameters['routing_key'], $arguments);
    }

    private function createDelayArtifacts(QueueConfiguration $queueConfig): int
    {
        $delay = $queueConfig->delay();
        $name = $queueConfig->name();

        $this->createQueue($name . '_delay_' . $delay, [
            'durable' => true,
            'arguments' => [
                'x-message-ttl' => $delay,
                'x-dead-letter-exchange' => 'delay',
                'x-dead-letter-routing-key' => $name,
            ],
        ]);

        $this->createBinding('delay', $name, $name);

        return $delay;
    }

    private function createDeadLetterQueueAndBindings(string $name): void
    {
        $this->createQueue($name . '_dl', [
            'durable' => true,
        ]);

        $this->createBinding('dl', $name . '_dl', $name);
    }

    private function createRetryQueues(QueueConfiguration $queueConfig): void
    {
        $name = $queueConfig->name();
        $retries = $queueConfig->retries();

        $retriesQueues = [];
        for($i = 0; $i < count($retries); ++$i)
        {
            if(0 === $i)
            {
                $this->createBinding('retry', $name, $name);
            }

            $retryQueueName = $name . '_retry_' . $retries[$i];

            if(! in_array($retryQueueName, $retriesQueues))
            {
                $this->createQueue($retryQueueName, [
                    'durable' => true,
                    'arguments' => [
                        'x-message-ttl' => $retries[$i] * 1000,
                        'x-dead-letter-exchange' => 'retry',
                        'x-dead-letter-routing-key' => $name,
                    ],
                ]);
            }

            $retryRoutingkey = $name . '_retry_' . ($i + 1);
            $this->createBinding('retry', $retryQueueName, $retryRoutingkey);
        }
    }
}
