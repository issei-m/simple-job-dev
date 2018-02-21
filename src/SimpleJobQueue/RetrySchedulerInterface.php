<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface RetrySchedulerInterface
{
    /**
     * Returns the datetime when the given retry job should be ran at, if that's immediate, a NULL should be returned.
     *
     * @param Job $retryJob     The job which will be ran for retry.
     * @param int $retriedCount The number of retry of the job.
     *
     * @return \DateTimeInterface|null
     */
    public function scheduleNextRetry(Job $retryJob, int $retriedCount): ?\DateTimeInterface;
}
