<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueueBundle\DependencyInjection;

use Issei\SimpleJobQueueBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testWithMinimal()
    {
        $input = [
            'type' => 'rdb',
        ];

        $processedConfig = (new Processor())->processConfiguration(new Configuration(), [$input]);

        self::assertSame([
            'default' => [
                'type' => 'rdb',
            ],
        ], $processedConfig['queues']);
        self::assertEquals('default', $processedConfig['default_queue']);
        self::assertFalse($processedConfig['reporter']['enabled']);
        self::assertFalse($processedConfig['scheduler']['enabled']);
    }

    public function testWithEnablingReporterAndScheduler()
    {
        $input = [
            'type' => 'rdb',
            'reporter' => null,
            'scheduler' => 'rdb',
        ];

        $processedConfig = (new Processor())->processConfiguration(new Configuration(), [$input]);

        self::assertSame([
            'default' => [
                'type' => 'rdb',
            ],
        ], $processedConfig['queues']);
        self::assertSame([
            'enabled' => true,
        ], $processedConfig['reporter']);
        self::assertSame([
            'type' => 'rdb',
            'enabled' => true,
        ], $processedConfig['scheduler']);
    }

    /**
     * @dataProvider defaultQueueProvider
     */
    public function testWithMultipleQueues(?string $defaultQueue, string $expectedDefaultQueue)
    {
        $input = [
            'queues' => [
                'high' => [
                    'type' => 'rdb',
                ],
                'low' => [
                    'type' => 'rdb',
                ],
            ],
        ];

        if (null !== $defaultQueue) {
            $input['default_queue'] = $defaultQueue;
        }

        $processedConfig = (new Processor())->processConfiguration(new Configuration(), [$input]);

        self::assertSame([
            'high' => [
                'type' => 'rdb',
            ],
            'low' => [
                'type' => 'rdb',
            ],
        ], $processedConfig['queues']);
        self::assertEquals($expectedDefaultQueue, $processedConfig['default_queue']);
    }

    public static function defaultQueueProvider(): array
    {
        return [
            'no specified' => [null, 'high'],
            'high' => ['high', 'high'],
            'low' => ['low', 'low'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider containingInvalidArrayNodesProvider
     *
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function some_variable_nodes_should_be_an_array(array $input)
    {
        (new Processor())->processConfiguration(new Configuration(), [$input]);
    }

    public static function containingInvalidArrayNodesProvider(): array
    {
        return [
            'queues.*.schema' => [
                [
                    'type' => 'rdb',
                    'schema' => '',
                ],
            ],
            'reporter.params' => [
                [
                    'type' => 'rdb',
                    'reporter' => [
                        'params' => '',
                    ],
                ],
            ],
            'reporter.schema' => [
                [
                    'type' => 'rdb',
                    'reporter' => [
                        'schema' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider notContainingKeyNodeForRedisProvider
     *
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function redis_stuff_should_key_node(array $input)
    {
        (new Processor())->processConfiguration(new Configuration(), [$input]);
    }

    public static function notContainingKeyNodeForRedisProvider(): array
    {
        return [
            'queues' => [
                [
                    'type' => 'redis',
                ],
            ],
            'scheduler' => [
                [
                    'type' => 'rdb',
                    'scheduler' => 'scheduler',
                ],
            ],
        ];
    }
}
