<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobQueue;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\ProcessFactoryInterface;
use Issei\SimpleJobQueue\RetrySchedulerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Process\Process;

class JobTest extends TestCase
{
    /**
     * @test
     */
    public function cloned_one_should_have_a_difference_identifier_than_original(): void
    {
        $job = new Job('do:something');
        self::assertNotSame((clone $job)->getId(), $job->getId());
    }

    /**
     * @test
     */
    public function createProcess_should_return_the_process_created_using_given_process_factory(): void
    {
        $processFactory = $this->prophesize(ProcessFactoryInterface::class);
        $expectedCreatedProcess = $this->createMock(Process::class);

        $processFactory->createProcess('do:something', ['--foo=bar'])->willReturn($expectedCreatedProcess);

        $job = new Job('do:something', ['--foo=bar']);
        self::assertSame($expectedCreatedProcess, $job->createProcess($processFactory->reveal()));
    }

    /**
     * @test
     */
    public function isRetryable_should_return_true_if_retryable_count_is_remaining()
    {
        $job = new Job('do:something', [], 1);
        self::assertTrue($job->isRetryable());

        [$retryJob] = (new Job('do:something', [], 2))->retry($this->prophesize(RetrySchedulerInterface::class)->reveal());
        self::assertTrue($retryJob->isRetryable());
    }

    /**
     * @test
     */
    public function isRetryable_should_return_false_if_retryable_count_is_not_remaining()
    {
        $job = new Job('do:something', [], 0);
        self::assertFalse($job->isRetryable());

        [$retryJob] = (new Job('do:something', [], 1))->retry($this->prophesize(RetrySchedulerInterface::class)->reveal());
        self::assertFalse($retryJob->isRetryable());
    }

    /**
     * @test
     */
    public function retry_should_return_the_tuple_where_cloned_one_to_retry_and_scheduled_datetime_to_run_it()
    {
        $retryScheduler = $this->prophesize(RetrySchedulerInterface::class);

        $expectedExecuteAt = new \DateTimeImmutable('+1 hour');

        $job = new Job('do:something', ['--foo=bar'], 1);

        $retryScheduler->scheduleNextRetry(Argument::that(function (Job $cloned) use ($job) {
            return $cloned !== $job && false === $cloned->isRetryable();
        }), 1)->shouldBeCalledTimes(1)->willReturn($expectedExecuteAt);

        [$cloned, $executeAt] = $job->retry($retryScheduler->reveal());
        \assert($cloned instanceof Job);
        self::assertSame($expectedExecuteAt, $executeAt);
    }
}
