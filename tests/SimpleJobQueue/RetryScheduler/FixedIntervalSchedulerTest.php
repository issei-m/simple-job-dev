<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueue\RetryScheduler;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\RetryScheduler\FixedIntervalScheduler;
use PHPUnit\Framework\TestCase;

class FixedIntervalSchedulerTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_return_fixed_schedule()
    {
        $retryJob = $this->createMock(Job::class);

        $noInterval = new FixedIntervalScheduler(0);
        self::assertGreaterThanOrEqual(new \DateTimeImmutable('now'), $noInterval->scheduleNextRetry($retryJob, 1));
    }
}
