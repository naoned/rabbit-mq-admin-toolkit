<?php

namespace Bab\RabbitMq\Specification;

use Bab\RabbitMq\Configuration;

final class DelayExchangeCanBeCreated implements Specification
{
    public function isSatisfiedBy(Configuration $config): bool
    {
        if(! isset($config['queues']) || empty($config['queues']))
        {
            return false;
        }

        foreach($config['queues'] as $name => $parameters)
        {
            if(isset($parameters['delay']))
            {
                return true;
            }
        }

        return false;
    }
}
