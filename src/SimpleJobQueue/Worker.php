<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Worker
{
    /**
     * @var string
     */
    private $name;

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
     * @var int
     */
    private $maxJobs;

    /**
     * @var LoggerInterface
     */
    private $logger;

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

    public function __construct(
        string $name,
        QueueInterface $queue,
        ReporterInterface $reporter,
        ProcessFactoryInterface $processFactory,
        int $maxJobs = 4,
        LoggerInterface $logger = null
    ) {
        $this->name = $name;
        $this->queue = $queue;
        $this->reporter = $reporter;
        $this->processFactory = $processFactory;
        $this->runningJobs = new \SplObjectStorage();
        $this->maxJobs = $maxJobs;
        $this->logger = $logger ?: new NullLogger();
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
            $this->logger->debug('Caught SIGTERM, worker goes into termination.');
        });

        $this->startedTime = time();

        $this->logger->info('Worker started: ' . $this->name);

        while (!$this->shouldTerminate || 0 < \count($this->runningJobs)) {
            \pcntl_signal_dispatch();

            $this->checkRunningJobs();

            if (!$this->shouldTerminate && \count($this->runningJobs) < $this->maxJobs) {
                $job = $this->queue->dequeue();
                if (null === $job) {
                    continue;
                }

                $this->logger->info('Dequeued job: ' . $job->getId());

                $process = $job->createProcess($this->processFactory);
                $this->runningJobs[$job] = $process;

                $process->start();

                $this->reporter->reportJobRunning($job, $this->name);
                $this->logger->info(\sprintf('%s START', $job->getName()), ['job' => (string) $job->getId(), 'pid' => $process->getPid()]);
            }

            if (\time() > $this->startedTime + $maxRuntimeInSec) {
                $this->shouldTerminate = true;
                $this->logger->debug('Elapsed maximum runtime, worker goes into termination.');
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

            if ('' !== $incrementalStdOut) {
                $this->logger->debug(\sprintf('%s OUT > %s', $job->getName(), rtrim($incrementalStdOut)), ['job' => (string) $job->getId()]);
            }
            if ('' !== $incrementalStdErr) {
                $this->logger->debug(\sprintf('<error>%s ERR > %s</error>', $job->getName(), rtrim($incrementalStdErr)), ['job' => (string) $job->getId()]);
            }

            if ($process->isRunning()) {
                $this->reporter->updateJobOutput($job->getId(), $incrementalStdOut, $incrementalStdErr);
            } else {
                unset($this->runningJobs[$job]);
                $this->handleTerminatedJob($job, $process, $incrementalStdOut, $incrementalStdErr);
            }
        }
    }

    private function handleTerminatedJob(Job $job, Process $process, string $lastStdOut, string $lastStdErr): void
    {
        $this->reporter->reportJobFinished($job->getId(), $process->getExitCode(), $lastStdOut, $lastStdErr);

        if ($process->isSuccessful()) {
            $this->logger->info(\sprintf('%s FINISHED', $job->getName()), ['job' => (string) $job->getId()]);
        } else {
            $this->logger->info(\sprintf('<error>%s FAILED</error>', $job->getName()), ['job' => (string) $job->getId(), 'exit_code' => $process->getExitCode()]);

            if ($job->isRetryable()) {
                $retryJob = $job->retry($this->queue);
                $this->reporter->reportJobRetrying($job->getId(), $retryJob->getId());
                $this->logger->info(\sprintf('<error>%s RETRYING to %s</error>', $job->getId(), $retryJob->getId()));
            }
        }
    }
}
