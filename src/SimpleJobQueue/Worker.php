<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

use Symfony\Component\Process\Process;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Worker
{
    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var ProcessFactoryInterface
     */
    private $processFactory;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $maxJobs;

    /**
     * @var \SplObjectStorage
     */
    private $runningJobs;

    /**
     * @var bool
     */
    private $shouldTerminate = false;

    /**
     * @var int
     */
    private $startedTime;

    public function __construct(string $name, QueueInterface $queue, ReporterInterface $reporter, ProcessFactoryInterface $processFactory, int $maxJobs = 4)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->reporter = $reporter;
        $this->processFactory = $processFactory;
        $this->runningJobs = new \SplObjectStorage();
        $this->maxJobs = $maxJobs;
    }

    /**
     * Starts polling to run jobs.
     *
     * @param int $maxRuntimeInSec The maximum runtime in second, the polling will be terminated after this time was elapsed.
     */
    public function start(int $maxRuntimeInSec = 60 * 15): void
    {
        \pcntl_signal(SIGTERM, function () {
            $this->shouldTerminate = true;
        });

        $this->startedTime = time();

        while (!$this->shouldTerminate || 0 < \count($this->runningJobs)) {
            \pcntl_signal_dispatch();

            $this->checkRunningJobs();

            if (!$this->shouldTerminate && \count($this->runningJobs) < $this->maxJobs) {
                $job = $this->queue->dequeue();

                if (null !== $job) {
                    $process = $job->createProcess($this->processFactory);
                    $this->runningJobs[$job] = $process;

                    $process->start();
                    $this->reporter->reportJobRunning($job, $this->name, $process->getPid());
                }
            }

            if (\time() > $this->startedTime + $maxRuntimeInSec) {
                $this->shouldTerminate = true;
            }

            \usleep(500000);
        }
    }

    private function checkRunningJobs(): void
    {
        foreach ($this->runningJobs as $job) {
            \assert($job instanceof Job);

            $process = $this->runningJobs[$job];
            \assert($process instanceof Process);

            $incrementalStdOut = $process->getIncrementalOutput();
            $incrementalStdErr = $process->getIncrementalErrorOutput();

            if ($process->isRunning()) {
                $this->reporter->updateJobOutput($job->getId(), $incrementalStdOut, $incrementalStdErr);
            } else {
                unset($this->runningJobs[$job]);
                $this->reporter->reportJobFinished($job->getId(), $process->getExitCode(), $incrementalStdOut, $incrementalStdErr);

                if (!$process->isSuccessful() && $job->isRetryable()) {
                    $retryJob = $job->retry($this->queue);
                    $this->reporter->reportJobRetrying($job->getId(), $retryJob->getId());
                }
            }
        }
    }
}
