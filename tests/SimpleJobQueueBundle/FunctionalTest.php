<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueueBundle;

use Doctrine\DBAL\Connection;
use Issei\SimpleJobQueue\Backend\RDB\Queue as RDBQueue;
use Issei\SimpleJobQueue\Backend\RDB\Reporter as RDBReporter;
use Issei\SimpleJobQueue\Backend\Redis\Queue as RedisQueue;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueueBundle\Command\RunCommand;
use Issei\SimpleJobQueueBundle\Command\ScheduleCommand;
use Issei\SimpleJobQueueBundle\JobQueue\NullReporter;
use Issei\SimpleJobSchedule\Scheduler;
use Predis\Client as RedisClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Issei\SimpleJobQueueBundle\FixtureApp\AppKernel;

class FunctionalTest extends KernelTestCase
{
    private static $containerClassName;

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected static function bootKernel(iterable $extraConfig = [], string $containerClassName = 'genericContainer'): KernelInterface
    {
        static::ensureKernelShutdown();

        $kernel = self::createKernel();
        \assert($kernel instanceof AppKernel);

        foreach ($extraConfig as $config) {
            $kernel->addExtraConfig($config);
        }

        if ($containerClassName) {
            self::$containerClassName = $containerClassName;
            $kernel->setContainerClassName($containerClassName);
        }

        $kernel->boot();

        return static::$kernel = $kernel;
    }

    private static function getXpathForContainerXml()
    {
        \assert(null !== static::$kernel);

        $xml = XmlUtils::loadFile(static::$kernel->getCacheDir() . '/' . self::$containerClassName . '.xml');
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('dic', XmlFileLoader::NS);

        return $xpath;
    }

    public function testWithMinimal()
    {
        self::bootKernel([
            'minimal.yml',
        ], 'minimalContainer');

        $xpath = self::getXpathForContainerXml();

        self::assertEquals('issei_simple_job_queue.queue.default', $xpath->query('//dic:service[@id="' . QueueInterface::class . '"]')->item(0)->getAttribute('alias'));
        self::assertEquals(RDBQueue::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default"]')->item(0)->getAttribute('class'));
        self::assertEquals(Connection::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default.rdb_connection"]')->item(0)->getAttribute('class'));
        self::assertEquals(NullReporter::class, $xpath->query('//dic:service[@id="' . ReporterInterface::class . '"]')->item(0)->getAttribute('class'));
        self::assertCount(0, $xpath->query('//dic:service[@id="' . Scheduler::class . '"]'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . RunCommand::class . '"]'));
        self::assertCount(0, $xpath->query('//dic:service[@id="' . ScheduleCommand::class . '"]'));
    }

    public function testWithRdbShared()
    {
        self::bootKernel([
            'rdb_shared.yml',
        ], 'rdbSharedContainer');

        $xpath = self::getXpathForContainerXml();

        self::assertEquals(RDBQueue::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default"]')->item(0)->getAttribute('class'));
        self::assertEquals(Connection::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default.rdb_connection"]')->item(0)->getAttribute('class'));
        self::assertEquals(RDBReporter::class, $xpath->query('//dic:service[@id="' . ReporterInterface::class . '"]')->item(0)->getAttribute('class'));
        self::assertEquals('issei_simple_job_queue.queue.default.rdb_connection', $xpath->query('//dic:service[@id="issei_simple_job_queue.reporter.rdb_connection"]')->item(0)->getAttribute('alias'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . Scheduler::class . '"]'));
        self::assertEquals('issei_simple_job_queue.queue.default.rdb_connection', $xpath->query('//dic:service[@id="issei_simple_job_queue.scheduler.rdb_connection"]')->item(0)->getAttribute('alias'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . RunCommand::class . '"]'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . ScheduleCommand::class . '"]'));
    }

    public function testWithRedisShared()
    {
        self::bootKernel([
            'redis_shared.yml',
        ], 'redisSharedContainer');

        $xpath = self::getXpathForContainerXml();

        self::assertEquals(RedisQueue::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default"]')->item(0)->getAttribute('class'));
        self::assertEquals(RedisClient::class, $xpath->query('//dic:service[@id="issei_simple_job_queue.queue.default.redis_connection"]')->item(0)->getAttribute('class'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . Scheduler::class . '"]'));
        self::assertEquals('issei_simple_job_queue.queue.default.redis_connection', $xpath->query('//dic:service[@id="issei_simple_job_queue.scheduler.redis_connection"]')->item(0)->getAttribute('alias'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . RunCommand::class . '"]'));
        self::assertCount(1, $xpath->query('//dic:service[@id="' . ScheduleCommand::class . '"]'));
    }

    /**
     * @test
     */
    public function some_internal_parameters_should_be_removed()
    {
        self::bootKernel([
            'minimal.yml',
        ]);

        $xpath = self::getXpathForContainerXml();

        self::assertCount(0, $xpath->query('//dic:parameter[@key="issei_simple_job_queue.default_queue"]'));
    }
}
