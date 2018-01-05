<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle\JobQueue;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\JobId;
use Issei\SimpleJobQueue\ReporterInterface;

/**
 * Reports nothing.
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class NullReporter implements ReporterInterface
{
    /**
     * {@inheritdoc}
     */
    public function reportJobRunning(Job $job, string $workerName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newOutput, string $newErrorOutput): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRetrying(JobId $jobId, JobId $retryJobId): void
    {
    }
}
