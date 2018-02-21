<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueue\Util;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\RetrySchedulerInterface;
use Issei\SimpleJobQueue\Util\JobSerializer;
use PHPUnit\Framework\TestCase;

class JobSerializerTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testGeneric(Job $job, array $expectedSerialized): void
    {
        $serializer = new JobSerializer();

        $serialized = $serializer->serialize($job);
        self::assertSame($expectedSerialized, $serialized);

        $deserialized = $serializer->deserialize($serialized);
        self::assertInstanceOf(Job::class, $deserialized);
        self::assertEquals($job, $deserialized);
        self::assertNotSame($job, $deserialized);
    }

    public static function dataProvider(): array
    {
        $job1 = new Job('Foo');
        $job2 = new Job('Bar', ['--a=b', '-cde', 'fgh'], 5);
        [$job3] = $job2->retry(new class implements RetrySchedulerInterface {
            public function scheduleNextRetry(Job $retryJob, int $retriedCount): ?\DateTimeInterface
            {
                return null;
            }
        });
        \assert($job3 instanceof Job);

        return [
            [
                $job1,
                [
                    'id' => $job1->getId()->__toString(),
                    'name' => 'Foo',
                    'arguments' => \json_encode([]),
                    'max_retries' => 0,
                    'retries' => 0,
                ],
            ],
            [
                $job2,
                [
                    'id' => $job2->getId()->__toString(),
                    'name' => 'Bar',
                    'arguments' => \json_encode(['--a=b', '-cde', 'fgh']),
                    'max_retries' => 5,
                    'retries' => 0,
                ],
            ],
            [
                $job3,
                [
                    'id' => $job3->getId()->__toString(),
                    'name' => 'Bar',
                    'arguments' => \json_encode(['--a=b', '-cde', 'fgh']),
                    'max_retries' => 5,
                    'retries' => 1,
                ],
            ],
        ];
    }
}
