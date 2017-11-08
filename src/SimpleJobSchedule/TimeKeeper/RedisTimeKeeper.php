<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule\TimeKeeper;

use Issei\SimpleJobSchedule\TimeKeeperInterface;
use Predis\Client as Redis;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class RedisTimeKeeper implements TimeKeeperInterface
{
    /**
     * @var Redis
     */
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastRanTime(string $key): ?\DateTimeInterface
    {
        $lastRanAtAsEpoch = $this->redis->get($key);

        return $lastRanAtAsEpoch
            ? (new \DateTimeImmutable())->setTimestamp((int) $lastRanAtAsEpoch)
            : null
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function attemptToKeepRunTime(string $key, \DateTimeInterface $runTime): bool
    {
        $script = <<< 'LUA'
local last_run_at = redis.call('GET', KEYS[1])

if last_run_at ~= ARGV[1] then
    redis.call('SET', KEYS[1], ARGV[1])
    return 1
else
    return 0
end
LUA;

        return (bool) $this->redis->eval($script, 1, $key, $runTime->getTimestamp());
    }
}
