<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobQueue\JobId;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\ReporterInterface;
use Issei\SimpleJobQueue\Util\JobSerializer;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class Hybrid implements QueueInterface, ReporterInterface
{
    private const STATE_PENDING = 'pending';
    private const STATE_WAITING_FOR_RUN = 'waiting_for_run';

    use QueueTrait, ReporterTrait {
        QueueTrait::createEnqueueQueryBuilder as createBaseEnqueueQb;
        QueueTrait::createDequeueFetchQueryBuilder as createBaseDequeueFetchQb;
    }

    public function __construct(Connection $conn, Schema\QueueSchema $queueSchema = null, Schema\ReporterSchema $reporterSchema = null, JobSerializer $jobSerializer = null)
    {
        $this->conn = $conn;
        $this->jobSerializer = $jobSerializer ?: new JobSerializer();

        $this->queueSchema = $queueSchema ?: new Schema\QueueSchema();
        $this->reporterSchema = $reporterSchema ?: new Schema\ReporterSchema();
        $this->reporterSchema->table = $this->queueSchema->table;
        $this->reporterSchema->jobIdColumn = $this->queueSchema->idColumn;
    }

    private function createEnqueueQueryBuilder(array $serialized, \DateTimeInterface $executesAt): QueryBuilder
    {
        return $this->createBaseEnqueueQb($serialized, $executesAt)
            ->setValue($this->reporterSchema->stateColumn, ':state')
            ->setParameter('state', self::STATE_PENDING)
        ;
    }

    private function createDequeueFetchQueryBuilder(): QueryBuilder
    {
        return $this->createBaseDequeueFetchQb()
            ->andWhere($this->reporterSchema->stateColumn . ' = :state')
            ->setParameter('state', self::STATE_PENDING)
        ;
    }

    private function createDequeuePurgeQueryBuilder(string $id): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->stateColumn, 'state')
            ->where($this->reporterSchema->jobIdColumn . ' = :id')
            ->setParameters([
                'id' => $id,
                'state' => self::STATE_WAITING_FOR_RUN,
            ])
        ;
    }

    private function createReportJobRunningQueryBuilder(JobId $jobId, string $workerName): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->update($this->reporterSchema->table)
            ->set($this->reporterSchema->stateColumn, ':state')
            ->set($this->reporterSchema->startedAtColumn, ':started_at')
            ->set($this->reporterSchema->workerNameColumn, ':worker_name')
            ->set($this->reporterSchema->stdoutColumn, ':empty')
            ->set($this->reporterSchema->stderrColumn, ':empty')
            ->where($this->reporterSchema->jobIdColumn . ' = :id')
            ->setParameters([
                'id' => $jobId,
                'state' => JobStates::STATE_RUNNING,
                'started_at' => new \DateTimeImmutable('now'),
                'worker_name' => $workerName,
                'empty' => '',
            ], [
                'started_at' => Type::getType('datetime_immutable'),
            ])
        ;
    }
}
