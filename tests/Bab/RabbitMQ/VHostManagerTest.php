<?php

declare(strict_types = 1);

namespace Bab\RabbitMQ;

use Bab\RabbitMq\Logger\CliLogger;
use Bab\RabbitMq\VhostManager;
use Bab\RabbitMq\Action\FakeAction;
use Bab\RabbitMq\Configuration\FromArray;
use Bab\RabbitMq\HttpClient\FakeClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use PHPUnit\Framework\Attributes\DataProvider;

class VHostManagerTest extends TestCase
{
    private VhostManager
        $vhostManager;
    private LoggerInterface
        $logger;
    private BufferedOutput
        $buffer;

    protected function setUp(): void
    {
        $httpClient = new FakeClient();

        $action = new FakeAction($httpClient);
        $action->setVhost('my_vhost');

        $credentials = [
            'host' => '127.0.0.1',
            'port' => 15672,
            'user' => 'root',
            'password' => 'root',
            'vhost' => 'my_vhost'
        ];

        $this->vhostManager = new VhostManager(
            $credentials,
            $action
        );

        $this->buffer = new BufferedOutput();
        $this->logger = new CliLogger($this->buffer);
        $this->vhostManager->setLogger($this->logger);
        $action->setLogger($this->logger);
    }

    #[DataProvider('providerTestCreateMapping')]
    public function testCreateMapping(array $config, string $expected): void
    {
        $this->buffer->fetch();

        $config = new FromArray($config);

        $this->vhostManager->createMapping($config);

        $output = $this->buffer->fetch();

        self::assertSame($expected, $output);
    }

    public static function providerTestCreateMapping(): array
    {
        return [
            [self::configOneQueue(), self::goldenMasterOutputOneQueue()],
            [self::configFullExample(), self::goldenMasterOutputFullExample()],
        ];
    }

    public static function configOneQueue(): array
    {
        return [
            'my_vhost' => [
                'parameters' => [
                    'with_dl' => false,
                    'with_unroutable' => false,
                    'queue_type' => "quorum"
                ],
                'exchanges' => [
                    'default' => ['type' => 'direct', 'durable' => true],
                ],
                'queues' => [
                    'test_queue' => [
                        'durable' => true,
                        'retries' => [5, 10, 15],
                        'bindings' => [
                            ['exchange' => 'default', 'routing_key' => 'test_queue'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function goldenMasterOutputOneQueue(): string
    {
        return <<<LOG_OUTPUT
With DL: false
With Unroutable: false
Create exchange dl
  parameters = [
    type: direct
    durable: 1
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange retry
  parameters = [
    durable: 1
    type: topic
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange default
  parameters = [
    type: direct
    durable: 1
  ]

Create queue test_queue
  parameters = [
    durable: 1
    arguments = [
      x-dead-letter-exchange: dl
      x-dead-letter-routing-key: test_queue
      x-queue-type: quorum
    ]
  ]

Create queue test_queue_dl
  parameters = [
    durable: 1
    arguments = [
      x-queue-type: quorum
    ]
  ]

Create binding between exchange dl and queue test_queue_dl (with routing_key: test_queue)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue
  ]

Create binding between exchange retry and queue test_queue (with routing_key: test_queue)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue
  ]

Create queue test_queue_retry_5
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 5000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: test_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue test_queue_retry_5 (with routing_key: test_queue_retry_1)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_retry_1
  ]

Create queue test_queue_retry_10
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 10000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: test_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue test_queue_retry_10 (with routing_key: test_queue_retry_2)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_retry_2
  ]

Create queue test_queue_retry_15
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 15000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: test_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue test_queue_retry_15 (with routing_key: test_queue_retry_3)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_retry_3
  ]

Create binding between exchange default and queue test_queue (with routing_key: test_queue)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue
  ]


LOG_OUTPUT;
    }


    public static function configFullExample(): array
    {
        return [
            'my_vhost' => [
                'parameters' => [
                    'with_dl' => true,
                    'with_unroutable' => true,
                    'queue_type' => 'quorum',
                ],
                'exchanges' => [
                    'default' => ['type' => 'direct', 'durable' => true],
                    'my_exchange' => ['type' => 'direct', 'durable' => true, 'with_unroutable' => true],
                    'my_exchange_headers' => ['type' => 'headers', 'durable' => true],
                ],
                'queues' => [
                    'my_queue' => [
                        'durable' => true,
                        'delay' => 5000,
                        'bindings' => [
                            ['exchange' => 'my_exchange', 'routing_key' => 'my_routing_key'],
                            ['exchange' => 'my_exchange', 'routing_key' => 'other_routing_key'],
                        ],
                    ],
                    'another_queue' => [
                        'durable' => true,
                        'with_dl' => false,
                        'retries' => [25, 125, 625],
                        'bindings' => [
                            [
                                'exchange' => 'my_exchange_headers',
                                'x_match' => 'all',
                                'matches' => [
                                    'header_name' => 'value',
                                    'other_header_name' => 'some_value',
                                ]
                            ],
                        ],
                    ],
                    'test_queue_with_retry' => [
                        'durable' => false,
                        'retries' => [42, 66],
                        'with_dl' => true,
                        'bindings' => [
                            ['exchange' => 'default', 'routing_key' => 'test_queue_with_retry'],
                        ],
                    ],
                ],
            ],
        ];
    }


    public static function goldenMasterOutputFullExample(): string
    {
        return <<<LOG_OUTPUT
With DL: true
With Unroutable: true
Create exchange unroutable
  parameters = [
    type: fanout
    durable: 1
  ]

Create queue unroutable
  parameters = [
    auto_delete: false
    durable: 1
    arguments = [
      x-queue-type: quorum
    ]
  ]

Create binding between exchange unroutable and queue unroutable (with routing_key: none)
  parameters = [
    arguments = [
    ]
  ]

Create exchange dl
  parameters = [
    type: direct
    durable: 1
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange retry
  parameters = [
    durable: 1
    type: topic
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange delay
  parameters = [
    durable: 1
  ]

Create exchange default
  parameters = [
    type: direct
    durable: 1
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange my_exchange
  parameters = [
    type: direct
    durable: 1
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create exchange my_exchange_headers
  parameters = [
    type: headers
    durable: 1
    arguments = [
      alternate-exchange: unroutable
    ]
  ]

Create queue my_queue
  parameters = [
    durable: 1
    arguments = [
      x-dead-letter-exchange: dl
      x-dead-letter-routing-key: my_queue
      x-queue-type: quorum
    ]
  ]

Create queue my_queue_delay_5000
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 5000
      x-dead-letter-exchange: delay
      x-dead-letter-routing-key: my_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange delay and queue my_queue (with routing_key: my_queue)
  parameters = [
    arguments = [
    ]
    routing_key: my_queue
  ]

Create queue my_queue_dl
  parameters = [
    durable: 1
    arguments = [
      x-queue-type: quorum
    ]
  ]

Create binding between exchange dl and queue my_queue_dl (with routing_key: my_queue)
  parameters = [
    arguments = [
    ]
    routing_key: my_queue
  ]

Create binding between exchange my_exchange and queue my_queue_delay_5000 (with routing_key: my_routing_key)
  parameters = [
    arguments = [
    ]
    routing_key: my_routing_key
  ]

Create binding between exchange my_exchange and queue my_queue_delay_5000 (with routing_key: other_routing_key)
  parameters = [
    arguments = [
    ]
    routing_key: other_routing_key
  ]

Create queue another_queue
  parameters = [
    durable: 1
    arguments = [
      x-dead-letter-exchange: dl
      x-dead-letter-routing-key: another_queue
      x-queue-type: quorum
    ]
  ]

Create queue another_queue_dl
  parameters = [
    durable: 1
    arguments = [
      x-queue-type: quorum
    ]
  ]

Create binding between exchange dl and queue another_queue_dl (with routing_key: another_queue)
  parameters = [
    arguments = [
    ]
    routing_key: another_queue
  ]

Create binding between exchange retry and queue another_queue (with routing_key: another_queue)
  parameters = [
    arguments = [
    ]
    routing_key: another_queue
  ]

Create queue another_queue_retry_25
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 25000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: another_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue another_queue_retry_25 (with routing_key: another_queue_retry_1)
  parameters = [
    arguments = [
    ]
    routing_key: another_queue_retry_1
  ]

Create queue another_queue_retry_125
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 125000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: another_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue another_queue_retry_125 (with routing_key: another_queue_retry_2)
  parameters = [
    arguments = [
    ]
    routing_key: another_queue_retry_2
  ]

Create queue another_queue_retry_625
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 625000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: another_queue
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue another_queue_retry_625 (with routing_key: another_queue_retry_3)
  parameters = [
    arguments = [
    ]
    routing_key: another_queue_retry_3
  ]

Create binding between exchange my_exchange_headers and queue another_queue (with routing_key: none)
  parameters = [
    arguments = [
      header_name: value
      other_header_name: some_value
      x-match: all
    ]
  ]

Create queue test_queue_with_retry
  parameters = [
    durable: 
    arguments = [
      x-dead-letter-exchange: dl
      x-dead-letter-routing-key: test_queue_with_retry
      x-queue-type: quorum
    ]
  ]

Create queue test_queue_with_retry_dl
  parameters = [
    durable: 1
    arguments = [
      x-queue-type: quorum
    ]
  ]

Create binding between exchange dl and queue test_queue_with_retry_dl (with routing_key: test_queue_with_retry)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_with_retry
  ]

Create binding between exchange retry and queue test_queue_with_retry (with routing_key: test_queue_with_retry)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_with_retry
  ]

Create queue test_queue_with_retry_retry_42
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 42000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: test_queue_with_retry
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue test_queue_with_retry_retry_42 (with routing_key: test_queue_with_retry_retry_1)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_with_retry_retry_1
  ]

Create queue test_queue_with_retry_retry_66
  parameters = [
    durable: 1
    arguments = [
      x-message-ttl: 66000
      x-dead-letter-exchange: retry
      x-dead-letter-routing-key: test_queue_with_retry
      x-queue-type: quorum
    ]
  ]

Create binding between exchange retry and queue test_queue_with_retry_retry_66 (with routing_key: test_queue_with_retry_retry_2)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_with_retry_retry_2
  ]

Create binding between exchange default and queue test_queue_with_retry (with routing_key: test_queue_with_retry)
  parameters = [
    arguments = [
    ]
    routing_key: test_queue_with_retry
  ]


LOG_OUTPUT;
    }
}
