<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule;

use Issei\SimpleJobQueue\QueueInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Scheduler
{
    /**
     * @var TimeKeeperInterface
     */
    private $timeKeeper;

    /**
     * @var QueueInterface
     */
    private $jobQueue;

    /**
     * @var ScheduleInterface[]
     */
    private $schedules = [];

    /**
     * @var int
     */
    private $startedTime;

    /**
     * @var bool
     */
    private $shouldTerminate;

    public function __construct(TimeKeeperInterface $timeKeeper, QueueInterface $jobQueue)
    {
        $this->timeKeeper = $timeKeeper;
        $this->jobQueue = $jobQueue;
    }

    public function addSchedule(ScheduleInterface $schedule): void
    {
        $this->schedules[] = $schedule;
    }

    public function daemon(int $maxRuntimeInSec = 60 * 60): void
    {
        pcntl_signal(SIGTERM, function () {
            $this->shouldTerminate = true;
        });

        $this->startedTime = time();

        $populatedSchedules = $this->prePopulateSchedules();

        while (!$this->shouldTerminate) {
            pcntl_signal_dispatch();

            foreach ($populatedSchedules as $key => [$schedule, $lastRanAt]) {
                assert($schedule instanceof ScheduleInterface);
                assert($lastRanAt instanceof \DateTimeInterface);

                if (!$schedule->shouldRun($lastRanAt)) {
                    continue;
                }

                $now = new \DateTimeImmutable('now');

                if ($this->timeKeeper->attemptToKeepRunTime($key, $now)) {
                    $this->jobQueue->enqueue($schedule->createJob());
                }

                $populatedSchedules[$key] = [$schedule, $now];
            }

            if (time() > $this->startedTime + $maxRuntimeInSec) {
                $this->shouldTerminate = true;
            }

            usleep(1000000);
        }
    }

    private function prePopulateSchedules(): array
    {
        $schedulerStartedAt = (new \DateTimeImmutable())->setTimestamp($this->startedTime);

        $populated = [];

        foreach ($this->schedules as $schedule) {
            $key = strtolower(get_class($schedule));
            $populated[$key] = [$schedule, $this->timeKeeper->getLastRanTime($key) ?? $schedulerStartedAt];
        }

        return $populated;
    }
}
