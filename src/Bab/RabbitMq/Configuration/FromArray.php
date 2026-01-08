<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;
use LogicException;

class FromArray implements Configuration
{
    private array
        $config;
    private string
        $vhost;
    private bool
        $hasDeadLetterExchange,
        $hasUnroutableExchange;
    private ?string
        $queueType;

    public function __construct(array $configuration)
    {
        $this->vhost = key($configuration);
        $this->config = current($configuration);

        $parameters = $this['parameters'];

        $this->hasDeadLetterExchange = false;
        if(isset($parameters['with_dl']))
        {
            $this->hasDeadLetterExchange = (bool) $parameters['with_dl'];
        }

        $this->hasUnroutableExchange = false;
        if(isset($parameters['with_unroutable']))
        {
            $this->hasUnroutableExchange = (bool) $parameters['with_unroutable'];
        }

        $this->queueType = null;
        if(isset($parameters['queue_type']))
        {
            $queueType = (string) $parameters['queue_type'];
            $this->ensureQueueTypeIsValid($queueType);
            $this->queueType = $queueType;
        }
    }

    private function ensureQueueTypeisValid(string $value): void
    {
        $allowedValues = ['classic', 'quorum'];

        if(! in_array($value, $allowedValues))
        {
            throw new LogicException("Invalid queue type : $value");
        }
    }

    public function vhost(): string
    {
        return $this->vhost;
    }

    public function hasDeadLetterExchange(): bool
    {
        return $this->hasDeadLetterExchange;
    }

    public function hasUnroutableExchange(): bool
    {
        return $this->hasUnroutableExchange;
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->config);
    }

    public function hasQueueTypeBeenDefined(): bool
    {
        return $this->queueType !== null;
    }

    public function queueType(): string
    {
        return $this->queueType;
    }

    public function offsetGet($offset): mixed
    {
        return $this->config[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        throw new LogicException('You shall not update configuration');
    }

    public function offsetUnset($offset): void
    {
        throw new LogicException('No need to unset');
    }
}
