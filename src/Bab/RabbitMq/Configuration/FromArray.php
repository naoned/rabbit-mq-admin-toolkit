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
    }

    public function getVhost(): string
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
        return \array_key_exists($offset, $this->config);
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
