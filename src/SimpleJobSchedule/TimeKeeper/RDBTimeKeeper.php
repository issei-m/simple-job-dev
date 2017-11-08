<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule\TimeKeeper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Issei\SimpleJobSchedule\TimeKeeperInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class RDBTimeKeeper implements TimeKeeperInterface
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $keyColumn;

    /**
     * @var string
     */
    private $timeColumn;

    public function __construct(Connection $conn, string $table = 'job_schedules', string $keyColumn = '`key`', string $timeColumn = 'last_ran_at')
    {
        $this->conn = $conn;
        $this->table = $table;
        $this->keyColumn = $keyColumn;
        $this->timeColumn = $timeColumn;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastRanTime(string $key): ?\DateTimeInterface
    {
        $lastRanAt = $this->createFetchLastRanAtForKeyQueryBuilder($key)
            ->execute()
            ->fetchColumn()
        ;

        return false !== $lastRanAt
            ? Type::getType('datetime_immutable')->convertToPHPValue($lastRanAt, $this->conn->getDatabasePlatform())
            : null
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function attemptToKeepRunTime(string $key, \DateTimeInterface $runTime): bool
    {
        $fetchQb = $this->createFetchLastRanAtForKeyQueryBuilder($key);

        // See: \Doctrine\DBAL\Query\QueryBuilder::fetchColumn
        $fetchQueryParameters = [
            $fetchQb->getSQL() . ' ' . $this->conn->getDatabasePlatform()->getWriteLockSQL(),
            $fetchQb->getParameters(),
        ];

        $insertQb = $this->conn->createQueryBuilder()
            ->insert($this->table)
            ->values([
                $this->keyColumn => ':key',
                $this->timeColumn => ':run_time',
            ])
            ->setParameters([
                'key' => $key,
                'run_time' => $runTime,
            ], [
                'run_time' => Type::getType('datetime_immutable'),
            ])
        ;

        $updateQb = $this->conn->createQueryBuilder()
            ->update($this->table)
            ->set($this->timeColumn, ':run_time')
            ->where($this->keyColumn . ' = :key')
            ->andWhere($this->timeColumn . ' = :current_time')
            ->setParameters([
                'key' => $key,
                'run_time' => $runTime,
            ], [
                'run_time' => Type::getType('datetime_immutable'),
            ])
        ;

        return $this->conn->transactional(function () use ($fetchQueryParameters, $insertQb, $updateQb) {
            $writeQb = $insertQb;

            if (false !== $currentTime = $this->conn->fetchColumn(...$fetchQueryParameters)) {
                $writeQb = $updateQb
                    ->setParameter('current_time', $currentTime)
                ;
            }

            try {
                return 1 === $writeQb->execute();
            } catch (RetryableException $e) {
                return false;
            }
        });
    }

    private function createFetchLastRanAtForKeyQueryBuilder(string $key): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->select('schedules.' . $this->timeColumn)
            ->from($this->table, 'schedules')
            ->where('schedules.' . $this->keyColumn . ' = :key')
            ->setParameter('key', $key)
            ->setMaxResults(1)
        ;
    }
}
