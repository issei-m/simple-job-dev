<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Console;

use Issei\SimpleJobQueue\JobId;
use Issei\SimpleJobQueue\ReporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class OutputReporter implements ReporterInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRunning(JobId $jobId, string $workerName, int $pid): void
    {
        $this->output->writeln(sprintf('%s START (PID: %d)', $jobId, $pid));
    }

    /**
     * {@inheritdoc}
     */
    public function updateJobOutput(JobId $jobId, string $newOutput, string $newErrorOutput): void
    {
        if ('' !== $newOutput) {
            $this->output->writeln(sprintf('%s OUT > %s', $jobId, rtrim($newOutput)));
        }
        if ('' !== $newErrorOutput) {
            $this->output->writeln(sprintf('<error>%s ERR</error> > %s', $jobId, rtrim($newErrorOutput)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobFinished(JobId $jobId, int $exitCode, string $newOutput, string $newErrorOutput): void
    {
        $this->updateJobOutput($jobId, $newOutput, $newErrorOutput);

        if (0 === $exitCode) {
            $this->output->writeln(sprintf('%s FINISHED', $jobId));
        } else {
            $this->output->writeln(sprintf('<error>%s FAILED (%d)</error>', $jobId, $exitCode));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reportJobRetrying(JobId $jobId, JobId $retryJobId): void
    {
        $this->output->writeln(sprintf('<error>%s RETRYING to %s</error>', $jobId, $retryJobId));
    }
}
