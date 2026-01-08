<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;
use LogicException;

class FromArray implements Configuration
{
    private $config;
    private $vhost;
    private $hasDeadLetterExchange;
    private $hasUnroutableExchange;
    private $queueType;

    public function __construct($configuration)
    {
        $this->vhost = key($configuration);
        $this->config = current($configuration);

        $parameters = $this['parameters'];

        $this->hasDeadLetterExchange = false;
        $this->hasUnroutableExchange = false;
        if (isset($parameters['with_dl'])) {
            $this->hasDeadLetterExchange = (bool) $parameters['with_dl'];
        }
        if (isset($parameters['with_unroutable'])) {
            $this->hasUnroutableExchange = (bool) $parameters['with_unroutable'];
        }

        $this->queueType = null;
        if(isset($parameters['queue_type'])) {
            $queueType = (string) $parameters['queue_type'];
            $this->ensureQueueTypeIsValid($queueType);
            $this->queueType = $queueType;
        }
    }

    private function ensureQueueTypeisValid($value)
    {
        $allowedValues = ['classic', 'quorum'];

        if(! in_array($value, $allowedValues))
        {
            throw new LogicException("Invalid queue type : $value");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasQueueTypeBeenDefined()
    {
        return $this->queueType !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function queueType()
    {
        return $this->queueType;
    }

    /**
     * {@inheritDoc}
     */
    public function getVhost()
    {
        return $this->vhost;
    }

    /**
     * {@inheritDoc}
     */
    public function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }

    /**
     * {@inheritDoc}
     */
    public function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] :  null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('You shall not update configuration');
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('No need to unset');
    }
}
