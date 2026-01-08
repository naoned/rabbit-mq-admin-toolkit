<?php

namespace Bab\RabbitMq\Tests\Specification;

use Bab\RabbitMq\Configuration;
use Bab\RabbitMq\Specification\DelayExchangeCanBeCreated;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DelayExchangeCanBeCreatedTest extends TestCase
{
    private DelayExchangeCanBeCreated
        $specification;

    public function setUp(): void
    {
        $this->specification = new DelayExchangeCanBeCreated();
    }

    #[DataProvider('provideConfig')]
    public function testItCreatesAnExchange(bool $expected, array $arrayConfig): void
    {
        $config = new Configuration\FromArray($arrayConfig);

        self::assertEquals($expected, $this->specification->isSatisfiedBy($config));
    }

    public static function provideConfig(): array
    {
        return [
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => null,
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => '',
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                true,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'delay' => 4,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                true,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                            'test_queue_2' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_2'],
                                ],
                            ],
                            'test_queue_with_retry' => [
                                'durable' => true,
                                'delay' => 8,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_with_retry'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'with_dl' => false,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                            'test_queue_2' => [
                                'durable' => true,
                                'with_dl' => false,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_2'],
                                ],
                            ],
                            'test_queue_with_retry' => [
                                'durable' => true,
                                'with_dl' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_with_retry'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
