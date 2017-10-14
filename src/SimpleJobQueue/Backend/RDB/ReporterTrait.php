<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobQueue\JobId;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait ReporterTrait
{
    use BaseTrait;

    /**
     * @var Schema\ReporterSchema
     */
    private $reporterSchema;

    /**
     * {@inheritdoc}
     */
    public function reportJobRunning(JobId $jobId, string $workerName, int $pid): void
    {
        $qb = $this->createReportJobRunningQueryBuilder($jobId, $workerName);

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    private function createReportJobRunningQueryBuilder(JobId $jobId, string $workerName): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->insert($this->reporterSchema->table)
            ->values([
                $this->reporterSchema->jobIdColumn => ':job_id',
                $this->reporterSchema->stateColumn => ':state',
                $this->reporterSchema->workerNameColumn => ':worker_name',
                $this->reporterSchema->startedAtColumn => ':started_at',
                $this->reporterSchema->stdoutColumn => ':empty',
                $this->reporterSchema->stderrColumn => ':empty',
            ])
            ->setParameters([
                'job_id' => $jobId,
                'state' => JobStates::STATE_RUNNING,
                'worker_name' => $workerName,
                'started_at' => new \DateTimeImmutable('now'),
                'empty' => '',
            ], [
                'started_at' => Type::getType('datetime_immutable'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void
    {
        $qb = $this->createUpdateJobOutputQueryBuilder($jobId, $newOutput, $newErrorOutput);

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    private function createUpdateJobOutputQueryBuilder(JobId $jobId, string $newOutput, string $newErrorOutput): QueryBuilder
    {
        $platform = $this->conn->getDatabasePlatform();

        return $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->stdoutColumn, $platform->getConcatExpression($this->reporterSchema->stdoutColumn, ':new_stdout'))
            ->set($this->reporterSchema->stderrColumn, $platform->getConcatExpression($this->reporterSchema->stderrColumn, ':new_stderr'))
            ->where($this->reporterSchema->jobIdColumn . ' = :job_id')
            ->setParameters([
                'job_id' => $jobId,
                'new_stdout' => $newOutput,
                'new_stderr' => $newErrorOutput,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newErrorOutput, string $newOutput): void
    {
        $qb = $this->createReportJobFinishedQueryBuilder($jobId, $exitCode, $newOutput, $newErrorOutput);

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    private function createReportJobFinishedQueryBuilder(JobId $jobId, int $exitCode, string $newErrorOutput, string $newOutput): QueryBuilder
    {
        $platform = $this->conn->getDatabasePlatform();

        return $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->stateColumn, ':state')
            ->set($this->reporterSchema->finishedAtColumn, ':finished_at')
            ->set($this->reporterSchema->exitCodeColumn, ':exit_code')
            ->set($this->reporterSchema->stdoutColumn, $platform->getConcatExpression($this->reporterSchema->stdoutColumn, ':new_stdout'))
            ->set($this->reporterSchema->stderrColumn, $platform->getConcatExpression($this->reporterSchema->stderrColumn, ':new_stderr'))
            ->where($this->reporterSchema->jobIdColumn . ' = :job_id')
            ->setParameters([
                'job_id' => $jobId,
                'state' => 0 === $exitCode ? JobStates::STATE_FINISHED : JobStates::STATE_FAILED,
                'finished_at' => new \DateTimeImmutable('now'),
                'exit_code' => $exitCode,
                'new_stdout' => $newOutput,
                'new_stderr' => $newErrorOutput,
            ], [
                'finished_at' => Type::getType('datetime_immutable'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRetrying(JobId $jobId, JobId $toJobId): void
    {
        $qb = $this->createReportJobRetryingQueryBuilder($jobId, $toJobId);

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    private function createReportJobRetryingQueryBuilder(JobId $jobId, JobId $toJobId): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->retryToColumn, ':to_job_id')
            ->where($this->reporterSchema->jobIdColumn . ' = :job_id')
            ->setParameters([
                'job_id' => $jobId,
                'to_job_id' => $toJobId,
            ])
        ;
    }
}
