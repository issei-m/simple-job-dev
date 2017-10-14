<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\Util\JobSerializer;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait QueueTrait
{
    use BaseTrait;

    /**
     * @var Schema\QueueSchema
     */
    private $queueSchema;

    /**
     * @var JobSerializer
     */
    private $jobSerializer;

    /**
     * {@inheritdoc}
     */
    public function enqueue(Job $job, \DateTimeInterface $executeAt = null): void
    {
        $qb = $this->createEnqueueQueryBuilder($this->jobSerializer->serialize($job), $executeAt ?: new \DateTimeImmutable('now'));

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    private function createEnqueueQueryBuilder(array $serialized, \DateTimeInterface $executesAt): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->insert($this->queueSchema->table)
            ->values([
                $this->queueSchema->idColumn => ':id',
                $this->queueSchema->nameColumn => ':name',
                $this->queueSchema->argumentsColumn => ':arguments',
                $this->queueSchema->executeAtColumn => ':execute_at',
                $this->queueSchema->maxRetriesColumn => ':max_retries',
                $this->queueSchema->retryIntervalColumn => ':retry_interval',
            ])
            ->setParameters([
                'id' => $serialized['id'],
                'name' => $serialized['name'],
                'arguments' => $serialized['arguments'],
                'execute_at' => $executesAt,
                'max_retries' => $serialized['max_retries'],
                'retry_interval' => $serialized['retry_interval'],
            ], [
                'execute_at' => Type::getType(Type::DATETIME_IMMUTABLE),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(): ?Job
    {
        $fetchQb = $this->createDequeueFetchQueryBuilder();

        // See: \Doctrine\DBAL\Query\QueryBuilder::execute
        $fetchQueryParameters = [
            $fetchQb->getSQL() . ' ' . $this->conn->getDatabasePlatform()->getWriteLockSQL(),
            $fetchQb->getParameters(),
            $fetchQb->getParameterTypes(),
        ];

        $assoc = $this->conn->transactional(function () use ($fetchQueryParameters) {
            $ret = $this->conn->fetchAssoc(...$fetchQueryParameters);

            if (false === $ret) {
                return null;
            }

            $this->createDequeuePurgeQueryBuilder($ret['id'])
                ->execute()
            ;

            return $ret;
        });

        return $assoc ? $this->jobSerializer->deserialize($assoc) : null;
    }

    private function createDequeueFetchQueryBuilder(): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->select(
                $this->queueSchema->idColumn . ' AS id',
                $this->queueSchema->nameColumn . ' AS name',
                $this->queueSchema->argumentsColumn . ' AS arguments',
                $this->queueSchema->maxRetriesColumn . ' AS max_retries',
                $this->queueSchema->retryIntervalColumn . ' AS retry_interval'
            )
            ->from($this->queueSchema->table)
            ->where($this->queueSchema->executeAtColumn . ' <= :execute_at')
            ->setParameter('execute_at', new \DateTimeImmutable('now'), Type::getType('datetime_immutable'))
            ->setMaxResults(1)
        ;
    }

    private function createDequeuePurgeQueryBuilder(string $id): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->delete($this->queueSchema->table)
            ->where($this->queueSchema->idColumn . ' = :id')
            ->setParameter('id', $id)
        ;
    }
}
