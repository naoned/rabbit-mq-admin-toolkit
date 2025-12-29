<?php

namespace Bab\RabbitMq\Tests\Configuration;

use Bab\RabbitMq\Configuration;
use PHPUnit\Framework\TestCase;

class FromArrayTest extends TestCase
{
    public function test_with_dl_and_unroutable(): void
    {
        $config = new Configuration\FromArray([
            'my_vhost' => [
                'parameters' => [
                    'with_dl' => true,
                    'with_unroutable' => true,
                ]
            ],
        ]);

        self::assertSame('my_vhost', $config->getVhost());
        self::assertTrue($config->hasDeadLetterExchange());
        self::assertTrue($config->hasUnroutableExchange());
    }
}
