<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule;

use Issei\SimpleJobQueue\Job;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface ScheduleInterface
{
    /**
     * Returns true if the job should run.
     *
     * @param \DateTimeInterface $lastRanAt
     *
     * @return bool
     */
    public function shouldRun(\DateTimeInterface $lastRanAt): bool;

    /**
     * Returns the new created job to be run.
     *
     * @return Job
     */
    public function createJob(): Job;
}
