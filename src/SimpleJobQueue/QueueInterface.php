<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

/**
 * Enqueues/Dequeues the job.
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface QueueInterface
{
    /**
     * Enqueues the job.
     *
     * @param Job                $job       The job.
     * @param \DateTimeInterface $executeAt The datetime when the job should be executed after,
     *                                      NULL (by default) means the job will immediately be started.
     *
     * @return void
     */
    public function enqueue(Job $job, \DateTimeInterface $executeAt = null): void;

    /**
     * Returns the available job to be ran, or NULL if no available jobs.
     *
     * @return Job|null
     */
    public function dequeue(): ?Job;
}
