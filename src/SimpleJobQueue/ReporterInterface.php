<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

/**
 * Reports transitions of job state.
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface ReporterInterface
{
    /**
     * Reports the job has started running on some worker.
     *
     * @param Job    $job
     * @param string $workerName
     */
    public function reportJobRunning(Job $job, string $workerName): void;

    /**
     * Updates the job output.
     *
     * @param JobId  $jobId
     * @param string $newOutput
     * @param string $newErrorOutput
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void;

    /**
     * Reports the job has finished (may be failed) with exit code and remaining output.
     *
     * @param JobId  $jobId
     * @param int    $exitCode
     * @param string $newOutput
     * @param string $newErrorOutput
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newOutput, string $newErrorOutput): void;

    /**
     * Reports the job has started retrying.
     *
     * @param JobId $jobId
     * @param JobId $retryJobId
     */
    public function reportJobRetrying(JobId $jobId, JobId $retryJobId): void;
}
