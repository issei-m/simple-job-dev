<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobQueue\Backend\RDB\Schema\ReporterSchema;
use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\JobId;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueue\Util\JobSerializer;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Reporter implements ReporterInterface
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var ReporterSchema
     */
    private $reporterSchema;

    /**
     * @var JobSerializer
     */
    private $jobSerializer;

    public function __construct(Connection $conn, ReporterSchema $reporterSchema = null, JobSerializer $jobSerializer = null)
    {
        $this->conn = $conn;
        $this->reporterSchema = $reporterSchema ?: new ReporterSchema();
        $this->jobSerializer = $jobSerializer ?: new JobSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRunning(Job $job, string $workerName, int $pid): void
    {
        $serialized = $this->jobSerializer->serialize($job);
        unset($serialized['id']);

        $qb = $this->conn->createQueryBuilder()
            ->insert($this->reporterSchema->table)
            ->values([
                $this->reporterSchema->jobIdColumn => ':job_id',
                $this->reporterSchema->stateColumn => ':state',
                $this->reporterSchema->serializedColumn => ':serialized',
                $this->reporterSchema->workerNameColumn => ':worker_name',
                $this->reporterSchema->startedAtColumn => ':started_at',
                $this->reporterSchema->stdoutColumn => ':empty',
                $this->reporterSchema->stderrColumn => ':empty',
            ])
            ->setParameters([
                'job_id' => $job->getId(),
                'state' => JobStates::STATE_RUNNING,
                'serialized' => $serialized['name'] . ':' . $serialized['arguments'],
                'worker_name' => $workerName,
                'started_at' => new \DateTimeImmutable('now'),
                'empty' => '',
            ], [
                'started_at' => Type::getType('datetime_immutable'),
            ])
        ;

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void
    {
        $platform = $this->conn->getDatabasePlatform();

        $qb = $this->conn->createQueryBuilder()
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

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newErrorOutput, string $newOutput): void
    {
        $platform = $this->conn->getDatabasePlatform();

        $qb = $this->conn->createQueryBuilder()
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

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRetrying(JobId $jobId, JobId $toJobId): void
    {
        $qb = $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->retryToColumn, ':to_job_id')
            ->where($this->reporterSchema->jobIdColumn . ' = :job_id')
            ->setParameters([
                'job_id' => $jobId,
                'to_job_id' => $toJobId,
            ])
        ;

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }
}
