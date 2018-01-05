<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Issei\SimpleJobQueue\Backend\RDB\Queue as RDBQueue;
use Issei\SimpleJobQueue\Backend\RDB\Reporter;
use Issei\SimpleJobQueue\Backend\RDB\Schema\QueueSchema;
use Issei\SimpleJobQueue\Backend\RDB\Schema\ReporterSchema;
use Issei\SimpleJobQueue\Backend\Redis\Queue as RedisQueue;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueueBundle\Command\ScheduleCommand;
use Issei\SimpleJobQueueBundle\QueueRegistry;
use Issei\SimpleJobSchedule\Scheduler;
use Issei\SimpleJobSchedule\TimeKeeper\RDBTimeKeeper;
use Issei\SimpleJobSchedule\TimeKeeper\RedisTimeKeeper;
use Issei\SimpleJobSchedule\TimeKeeperInterface;
use Predis\Client as Redis;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class IsseiSimpleJobQueueExtension extends Extension
{
    private $firstAppearedQueueConnectionId = [];

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $config = (new Processor())->processConfiguration(new Configuration(), $configs);

        $this->loadQueues($config['queues'], $container, $config['default_queue']);

        if ($config['reporter']['enabled']) {
            $this->loadReporter($config['reporter'], $container);
        }
        if ($config['scheduler']['enabled']) {
            $this->loadScheduler($config['scheduler'], $container);
        }
    }

    /**
     * Loads queues config.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     * @param string           $defaultQueueName
     */
    private function loadQueues(array $configs, ContainerBuilder $container, string $defaultQueueName): void
    {
        $container->setParameter('issei_simple_job_queue.default_queue', $defaultQueueName);

        $queueRefs = [];

        foreach ($configs as $name => $config) {
            $queueId = 'issei_simple_job_queue.queue.' . $name;
            $queueDef = $container->register($queueId)
                ->setPublic(false)
            ;

            if ('rdb' === $config['type']) {
                $connectionId = $this->loadRdbQueue($container, $queueDef, $name, $config);
            } else {
                $connectionId = $this->loadRedisQueue($container, $queueDef, $name, $config);
            }

            if (!isset($this->firstAppearedQueueConnectionId['rdb'])) {
                $this->firstAppearedQueueConnectionId['rdb'] = $connectionId;
            }

            if ($defaultQueueName === $name) {
                $container->setAlias(QueueInterface::class, $queueId);
            }

            $queueRefs[$name] = [
                new Reference($queueId),
                isset($config['process_factory']) ? new Reference($config['process_factory']) : null,
            ];
        }

        $container->getDefinition(QueueRegistry::class)
            ->setArguments([$queueRefs])
        ;
    }

    /**
     * Loads reporter config.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function loadReporter(array $config, ContainerBuilder $container): void
    {
        $reporterDef = $container->getDefinition(ReporterInterface::class);

        $connectionId = 'issei_simple_job_queue.reporter.rdb_connection';

        if (isset($config['params'])) {
            $container->register($connectionId, Connection::class)
                ->setPublic(false)
                ->setFactory([DriverManager::class, 'getConnection'])
                ->addArgument($config['params'])
            ;
        } else {
            $container->setAlias($connectionId, new Alias($config['connection_id'] ?? $this->getFirstAppearedQueueConnectionId('rdb'), false));
        }

        $reporterDef
            ->setClass(Reporter::class)
            ->setArguments([
                new Reference($connectionId),
                (function () use ($container, $config) {
                    if (!isset($config['schema'])) {
                        return null;
                    }

                    $container->register('issei_simple_job_queue.reporter.rdb_schema', ReporterSchema::class)
                        ->setPublic(false)
                        ->addArgument(self::camelizeKeys($config['schema']))
                    ;

                    return new Reference('issei_simple_job_queue.reporter.rdb_schema');
                })(),
            ])
        ;
    }

    /**
     * Loads scheduler config.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function loadScheduler(array $config, ContainerBuilder $container): void
    {
        \assert(interface_exists(TimeKeeperInterface::class), 'The package [issei-m/simple-job-schedule] must have been installed.');

        $container->register(Scheduler::class, Scheduler::class)
            ->setPublic(false)
            ->setArguments([
                new Reference(TimeKeeperInterface::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'job'])
        ;

        $timeKeeperDef = $container->register(TimeKeeperInterface::class)
            ->setPublic(false)
        ;

        if ('rdb' === $config['type']) {
            $this->loadRdbScheduleTimeKeeper($container, $timeKeeperDef, $config);
        } else {
            $this->loadRedisScheduleTimeKeeper($container, $timeKeeperDef, $config);
        }

        $container->register(ScheduleCommand::class, ScheduleCommand::class)
            ->setArguments([new Reference(Scheduler::class)])
            ->addTag('console.command')
        ;
    }

    private function loadRdbQueue(ContainerBuilder $container, Definition $queueDef, string $name, array $config): string
    {
        $connectionId = 'issei_simple_job_queue.queue.' . $name . '.rdb_connection';

        if (isset($config['params'])) {
            $container->register($connectionId, Connection::class)
                ->setPublic(false)
                ->setFactory([DriverManager::class, 'getConnection'])
                ->addArgument($config['params'])
            ;
        } else {
            $container->setAlias($connectionId, new Alias($config['connection_id'] ?? $this->getFirstAppearedQueueConnectionId('rdb'), false));
        }

        $queueDef
            ->setClass(RDBQueue::class)
            ->setArguments([
                new Reference($connectionId),
                $name,
                (function () use ($container, $name, $config) {
                    if (!isset($config['schema'])) {
                        return null;
                    }

                    $container->register('issei_simple_job_queue.queue.' . $name . '.rdb_schema', QueueSchema::class)
                        ->setPublic(false)
                        ->addArgument(self::camelizeKeys($config['schema']))
                    ;

                    return new Reference('issei_simple_job_queue.queue.' . $name . '.rdb_schema');
                })(),
            ])
        ;

        return $connectionId;
    }

    private function loadRedisQueue(ContainerBuilder $container, Definition $queueDef, string $name, array $config): string
    {
        $connectionId = 'issei_simple_job_queue.queue.' . $name . '.redis_connection';

        if (isset($config['params'])) {
            $container->register($connectionId, Redis::class)
                ->setPublic(false)
                ->setArguments([
                    $config['params'] ?? null,
                    $config['options'] ?? null,
                ])
            ;
        } else {
            $container->setAlias($connectionId, new Alias($config['connection_id'] ?? $this->getFirstAppearedQueueConnectionId('redis'), false));
        }

        $queueDef
            ->setClass(RedisQueue::class)
            ->setArguments([
                new Reference($connectionId),
                $name,
            ])
        ;

        return $connectionId;
    }

    private function loadRdbScheduleTimeKeeper(ContainerBuilder $container, Definition $timeKeeperDef, array $config): void
    {
        $connectionId = 'issei_simple_job_queue.scheduler.rdb_connection';

        if (isset($config['params'])) {
            $container->register($connectionId, Connection::class)
                ->setPublic(false)
                ->setFactory([DriverManager::class, 'getConnection'])
                ->addArgument($config['params'])
            ;
        } else {
            $container->setAlias($connectionId, new Alias($config['connection_id'] ?? $this->getFirstAppearedQueueConnectionId('rdb'), false));
        }

        $constructorParams = (new \ReflectionClass(RDBTimeKeeper::class))->getConstructor()->getParameters();

        $timeKeeperDef
            ->setClass(RDBTimeKeeper::class)
            ->setArguments([
                new Reference($connectionId),
                $config['table'] ?? $constructorParams[1]->getDefaultValue(),
                $config['key_column'] ?? $constructorParams[2]->getDefaultValue(),
                $config['time_column'] ?? $constructorParams[3]->getDefaultValue(),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'job'])
        ;
    }

    private function loadRedisScheduleTimeKeeper(ContainerBuilder $container, Definition $timeKeeperDef, array $config): void
    {
        $connectionId = 'issei_simple_job_queue.scheduler.redis_connection';

        if (isset($config['params'])) {
            $container->register($connectionId, Redis::class)
                ->setPublic(false)
                ->setArguments([
                    $config['params'] ?? null,
                    $config['options'] ?? null,
                ])
            ;
        } else {
            $container->setAlias($connectionId, new Alias($config['connection_id'] ?? $this->getFirstAppearedQueueConnectionId('rdb'), false));
        }

        $timeKeeperDef
            ->setClass(RedisTimeKeeper::class)
            ->setArguments([
                new Reference($connectionId),
                $config['key'],
            ])
        ;
    }

    private function getFirstAppearedQueueConnectionId(string $type): string
    {
        if (!$this->firstAppearedQueueConnectionId[$type]) {
            throw new \InvalidArgumentException(sprintf('The queue of type "%s" has not been registered yet.', $type));
        }

        return $this->firstAppearedQueueConnectionId[$type];
    }

    private static function camelizeKeys(array $array): array
    {
        return array_combine(
            array_map(
                function (string $v) { return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $v)))); },
                array_keys($array)
            ),
            array_values($array)
        );
    }
}
