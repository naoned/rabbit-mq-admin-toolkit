<?php

namespace Bab\RabbitMq\Specification;

use Bab\RabbitMq\Configuration;

final class RetryExchangeCanBeCreated implements Specification
{
    public function isSatisfiedBy(Configuration $config): bool
    {
        if(! isset($config['queues']) || empty($config['queues']))
        {
            return false;
        }

        foreach($config['queues'] as $parameters)
        {
            if(isset($parameters['retries']))
            {
                return true;
            }
        }

        return false;
    }
}
