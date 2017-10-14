<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class ReporterChain implements ReporterInterface
{
    /**
     * @var ReporterInterface[]
     */
    private $reporters;

    public function __construct(ReporterInterface ...$reporters)
    {
        $this->reporters = $reporters;
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRunning(JobId $jobId, string $workerName, int $pid): void
    {
        foreach ($this->reporters as $reporter) {
            $reporter->reportJobRunning($jobId, $workerName, $pid);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void
    {
        foreach ($this->reporters as $reporter) {
            $reporter->updateJobOutput($jobId, $newOutput, $newErrorOutput);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newOutput, string $newErrorOutput): void
    {
        foreach ($this->reporters as $reporter) {
            $reporter->reportJobFinished($jobId, $exitCode, $newOutput, $newErrorOutput);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRetrying(JobId $jobId, JobId $retryJobId): void
    {
        foreach ($this->reporters as $reporter) {
            $reporter->reportJobRetrying($jobId, $retryJobId);
        }
    }
}
