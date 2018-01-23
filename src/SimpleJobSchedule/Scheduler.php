<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule;

use Issei\SimpleJobQueue\QueueInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     * @var LoggerInterface
     */
    private $logger;

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

    public function __construct(TimeKeeperInterface $timeKeeper, LoggerInterface $logger = null)
    {
        $this->timeKeeper = $timeKeeper;
        $this->logger = $logger ?: new NullLogger();
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
            $this->logger->debug('Caught SIGTERM, daemon goes into termination.');
        });

        $this->startedTime = \time();

        $populatedSchedules = $this->prePopulateSchedules();
        $this->logger->info(sprintf('Scheduler started with %s schedule(s).', \count($populatedSchedules)));

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
                    $job = $schedule->createJob();
                    $jobQueue->enqueue($job);
                    $this->logger->info(sprintf('Enqueued: %s', $job->getName()), ['job' => (string) $job->getId()]);
                }

                $populatedSchedules[$key][2] = $now;
            }

            if (!$this->shouldTerminate && \time() > $this->startedTime + $maxRuntimeInSec) {
                $this->shouldTerminate = true;
                $this->logger->debug(sprintf('The max runtime (%d sec) elapsed, daemon goes into termination.', $maxRuntimeInSec));
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
