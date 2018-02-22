<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobQueue\Backend\RDB\Schema\QueueSchema;
use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\Util\JobSerializer;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Queue implements QueueInterface
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var string
     */
    private $name;

    /**
     * @var Schema\QueueSchema
     */
    private $queueSchema;

    /**
     * @var JobSerializer
     */
    private $jobSerializer;

    public function __construct(Connection $conn, string $name, Schema\QueueSchema $queueSchema = null, JobSerializer $jobSerializer = null)
    {
        $this->conn = $conn;
        $this->name = $name;
        $this->queueSchema = $queueSchema ?: new QueueSchema();
        $this->jobSerializer = $jobSerializer ?: new JobSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Job $job, \DateTimeInterface $executeAt = null): void
    {
        $serialized = $this->jobSerializer->serialize($job);

        $qb = $this->conn->createQueryBuilder()
            ->insert($this->queueSchema->table)
            ->values([
                $this->queueSchema->idColumn => ':id',
                $this->queueSchema->nameColumn => ':name',
                $this->queueSchema->argumentsColumn => ':arguments',
                $this->queueSchema->queueColumn => ':queue',
                $this->queueSchema->executeAtColumn => ':execute_at',
                $this->queueSchema->maxRetriesColumn => ':max_retries',
                $this->queueSchema->retriesColumn => ':retries',
            ])
            ->setParameters([
                'id' => $serialized['id'],
                'name' => $serialized['name'],
                'arguments' => $serialized['arguments'],
                'queue' => $this->name,
                'execute_at' => $executeAt ?: new \DateTimeImmutable('now'),
                'max_retries' => $serialized['max_retries'],
                'retries' => $serialized['retries'],
            ], [
                'execute_at' => Type::getType(Type::DATETIME_IMMUTABLE),
            ])
        ;

        $this->conn->transactional(function () use ($qb) {
            $qb->execute();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(): ?Job
    {
        $fetchQb = $this->conn->createQueryBuilder()
            ->select(
                $this->queueSchema->idColumn . ' AS id',
                $this->queueSchema->nameColumn . ' AS name',
                $this->queueSchema->argumentsColumn . ' AS arguments',
                $this->queueSchema->maxRetriesColumn . ' AS max_retries',
                $this->queueSchema->retriesColumn . ' AS retries'
            )
            ->from($this->queueSchema->table)
            ->where($this->queueSchema->executeAtColumn . ' <= :execute_at')
            ->andWhere($this->queueSchema->queueColumn . ' = :queue')
            ->setParameters([
                'queue' => $this->name,
                'execute_at' => new \DateTimeImmutable('now')
            ], [
                'execute_at' => Type::getType('datetime_immutable'),
            ])
            ->setMaxResults(1)
        ;

        // See: \Doctrine\DBAL\Query\QueryBuilder::execute
        $fetchQueryParameters = [
            $fetchQb->getSQL() . ' ' . $this->conn->getDatabasePlatform()->getWriteLockSQL(),
            $fetchQb->getParameters(),
            $fetchQb->getParameterTypes(),
        ];

        $purgeQb = $this->conn->createQueryBuilder()
            ->delete($this->queueSchema->table)
            ->where($this->queueSchema->idColumn . ' = :id')
        ;

        $assoc = $this->conn->transactional(function () use ($fetchQueryParameters, $purgeQb) {
            $ret = $this->conn->fetchAssoc(...$fetchQueryParameters);

            if (false === $ret) {
                return null;
            }

            $purgeQb->setParameter('id', $ret['id'])
                ->execute()
            ;

            return $ret;
        });

        return $assoc ? $this->jobSerializer->deserialize($assoc) : null;
    }
}
