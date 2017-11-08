<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule;

/**
 * The time-keeper for schedule, keeps the time when the job was last run (i.e. enqueued).
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface TimeKeeperInterface
{
    /**
     * Returns the time when the job was last run.
     * It returns a NULL if the job has never yet been run (at first time to be enlisted to the schedule set).
     *
     * @param string $key
     *
     * @return \DateTimeInterface|null
     */
    public function getLastRanTime(string $key): ?\DateTimeInterface;

    /**
     * Returns true if it succeeded to save the time when the job last run, otherwise false.
     *
     * On multiple schedulers make sure the only one works to save and returns true, while other one should not to do and return false,
     * meaning some locking structure may be needed to address conflict between multiple scheduler.
     *
     * @param string             $key
     * @param \DateTimeInterface $runTime
     *
     * @return bool
     */
    public function attemptToKeepRunTime(string $key, \DateTimeInterface $runTime): bool;
}
