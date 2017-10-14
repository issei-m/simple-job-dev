<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

use Symfony\Component\Process\Process;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class Job
{
    /**
     * @var JobId
     */
    private $id;

    /**
     * @var JobId
     */
    private $name;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var int
     */
    private $maxRetries;

    /**
     * @var int
     */
    private $retryInterval;

    /**
     * @param string   $name          The job name.
     * @param iterable $arguments     The job arguments.
     * @param int      $maxRetries    The number how many times the job can be retried for, 0 (by default) = never.
     * @param int      $retryInterval The interval seconds for retry.
     */
    public function __construct(string $name, iterable $arguments = [], int $maxRetries = 0, int $retryInterval = 0)
    {
        $this->id = new JobId();
        $this->name = $name;
        $this->arguments = is_array($arguments) ? $arguments : iterator_to_array($arguments);
        $this->maxRetries = $maxRetries;
        $this->retryInterval = $retryInterval;
    }

    public function __clone()
    {
        $this->id = new JobId();
    }

    /**
     * Returns the id.
     *
     * @return JobId
     */
    public function getId(): JobId
    {
        return $this->id;
    }

    /**
     * Returns the Symfony process generated using factory.
     *
     * @param ProcessFactoryInterface $processFactory
     *
     * @return Process
     */
    public function createProcess(ProcessFactoryInterface $processFactory): Process
    {
        return $processFactory->createProcess($this->name, $this->arguments);
    }

    /**
     * Returns true if the job is retryable.
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return 0 < $this->maxRetries;
    }

    /**
     * Retries the job using the queue.
     *
     * @param QueueInterface $queue
     *
     * @return Job
     *
     * @throws ExceptionInterface
     */
    public function retry(QueueInterface $queue): Job
    {
        if (!$this->isRetryable()) {
            throw new class('This job cannot be retried anymore.') extends \BadMethodCallException implements ExceptionInterface {};
        }

        $cloned = clone $this;
        $cloned->maxRetries--;

        $queue->enqueue($cloned, new \DateTimeImmutable(sprintf('+%d sec', $this->retryInterval)));

        return $cloned;
    }
}
