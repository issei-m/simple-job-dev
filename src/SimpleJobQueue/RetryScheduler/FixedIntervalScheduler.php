<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\RetryScheduler;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\RetrySchedulerInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class FixedIntervalScheduler implements RetrySchedulerInterface
{
    /**
     * @var int
     */
    private $intervalSeconds;

    public function __construct(int $intervalSeconds)
    {
        $this->intervalSeconds = $intervalSeconds;
    }

    /**
     * {@inheritdoc}
     */
    public function scheduleNextRetry(Job $retryJob, int $retriedCount): ?\DateTimeInterface
    {
        return new \DateTimeImmutable(\sprintf('+%d sec', $this->intervalSeconds));
    }
}
