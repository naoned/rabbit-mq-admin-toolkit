<?php

declare(strict_types = 1);

namespace Bab\RabbitMq\Amqp;

use Bab\RabbitMq\Configuration;

final class QueueConfiguration
{
    private string
        $name;
    private bool
        $withDL;
    private array
        $retries,
        $bindings;
    private array
        $parameters;
    private ?int
        $delay;

    public function __construct(string $name, array $parameters, Configuration $config)
    {
        $currentWithDl = $config->hasDeadLetterExchange();
        $retries = [];
        $bindings = [];

        if(isset($parameters['bindings']) && is_array($parameters['bindings']))
        {
            $bindings = $parameters['bindings'];
        }
        unset($parameters['bindings']);

        if(isset($parameters['with_dl']))
        {
            $currentWithDl = (bool)$parameters['with_dl'];
            unset($parameters['with_dl']);
        }

        if(isset($parameters['retries']))
        {
            $retries = $parameters['retries'];
            $currentWithDl = true;
            unset($parameters['retries']);
        }

        if($currentWithDl && ! isset($config['arguments']['x-dead-letter-exchange']))
        {
            if(! isset($parameters['arguments']))
            {
                $parameters['arguments'] = [];
            }

            $parameters['arguments']['x-dead-letter-exchange'] = 'dl';
            $parameters['arguments']['x-dead-letter-routing-key'] = $name;
        }

        $delay = null;
        if(isset($parameters['delay']))
        {
            $delay = (int) $parameters['delay'];

            unset($parameters['delay']);
        }

        $this->name = $name;
        $this->withDL = $currentWithDl;
        $this->bindings = $bindings;
        $this->retries = $retries;
        $this->parameters = $parameters;
        $this->delay = $delay;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withDL(): bool
    {
        return $this->withDL;
    }

    public function retries(): array
    {
        return $this->retries;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function delay(): ?int
    {
        return $this->delay;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function hasDelay(): bool
    {
        return $this->delay !== null;
    }
}
