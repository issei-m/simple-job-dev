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

    public function __construct(TimeKeeperInterface $timeKeeper)
    {
        $this->timeKeeper = $timeKeeper;
    }

    /**
     * Adds the schedule.
     *
     * @param ScheduleInterface $schedule The schedule.
     * @param QueueInterface    $jobQueue The corresponding queue.
     */
    public function addSchedule(ScheduleInterface $schedule, QueueInterface $jobQueue): void
    {
        $this->schedules[] = [$schedule, $jobQueue];
    }

    /**
     * Daemonizes.
     *
     * @param int $maxRuntimeInSec The maximum runtime in second, the daemon will be terminated after this time was elapsed.
     */
    public function daemon(int $maxRuntimeInSec = 60 * 60): void
    {
        \pcntl_signal(SIGTERM, function () {
            $this->shouldTerminate = true;
        });

        $this->startedTime = \time();

        $populatedSchedules = $this->prePopulateSchedules();

        while (!$this->shouldTerminate) {
            \pcntl_signal_dispatch();

            foreach ($populatedSchedules as $key => [$schedule, $jobQueue, $lastRanAt]) {
                \assert($schedule instanceof ScheduleInterface);
                \assert($jobQueue instanceof QueueInterface);
                \assert($lastRanAt instanceof \DateTimeInterface);

                if (!$schedule->shouldRun($lastRanAt)) {
                    continue;
                }

                $now = new \DateTimeImmutable('now');

                if ($this->timeKeeper->attemptToKeepRunTime($key, $now)) {
                    $jobQueue->enqueue($schedule->createJob());
                }

                $populatedSchedules[$key][2] = $now;
            }

            if (\time() > $this->startedTime + $maxRuntimeInSec) {
                $this->shouldTerminate = true;
            }

            \usleep(1000000);
        }
    }

    private function prePopulateSchedules(): array
    {
        $schedulerStartedAt = (new \DateTimeImmutable())->setTimestamp($this->startedTime);

        $populated = [];

        foreach ($this->schedules as [$schedule, $jobQueue]) {
            $key = \strtolower(\get_class($schedule));
            $populated[$key] = [
                $schedule,
                $jobQueue,
                $this->timeKeeper->getLastRanTime($key) ?? $schedulerStartedAt,
            ];
        }

        return $populated;
    }
}
