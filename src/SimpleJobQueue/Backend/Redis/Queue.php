<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\Redis;

use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\QueueInterface;
use Issei\SimpleJobQueue\Util\JobSerializer;
use Predis\Client as Redis;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Queue implements QueueInterface
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $key;

    /**
     * @var JobSerializer
     */
    private $serializer;

    public function __construct(Redis $redis, string $key, JobSerializer $serializer = null)
    {
        $this->redis = $redis;
        $this->key = $key;
        $this->serializer = $serializer ?: new JobSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Job $job, \DateTimeInterface $executeAt = null): void
    {
        $serialized = $this->serializer->serialize($job);

        $value = \json_encode($serialized);

        if ($executeAt) {
            $this->redis->zadd($this->getKeyForDelayed(), [$value => $executeAt->getTimestamp()]);
        } else {
            $this->redis->rpush($this->key, [$value]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(): ?Job
    {
        $this->checkDelayed();

        $deserialized = $this->redis->lpop($this->key);
        if (null === $deserialized) {
            return null;
        }

        return $this->serializer->deserialize(\json_decode($deserialized, true));
    }

    private function checkDelayed(): void
    {
        $script = <<< 'LUA'
local jobs_to_be_run = redis.call('ZRANGEBYSCORE', KEYS[1], 0, ARGV[1], 'LIMIT', 0, 1)

if (next(jobs_to_be_run) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, 0)
    redis.call('rpush', KEYS[2], jobs_to_be_run[1])
end
LUA;

        $this->redis->eval($script, 2, $this->getKeyForDelayed(), $this->key, time());
    }

    private function getKeyForDelayed(): string
    {
        return $this->key . ':delayed';
    }
}
