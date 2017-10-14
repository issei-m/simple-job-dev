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
     * @var JobSerializer
     */
    private $serializer;

    /**
     * @var string
     */
    private $name;

    public function __construct(Redis $redis, string $name, JobSerializer $serializer = null)
    {
        $this->redis = $redis;
        $this->name = $name;
        $this->serializer = $serializer ?: new JobSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Job $job, \DateTimeInterface $executeAt = null): void
    {
        $serialized = $this->serializer->serialize($job);

        if ($executeAt) {
            $serialized['execute_at'] =$executeAt->format('Y-m-d H:i:s');
        }

        $this->redis->rpush($this->name, [json_encode($serialized)]);
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue(): ?Job
    {
        $deserialized = $this->redis->lpop($this->name);
        if (null === $deserialized) {
            return null;
        }

        return $this->serializer->deserialize(json_decode($deserialized, true));
    }
}
