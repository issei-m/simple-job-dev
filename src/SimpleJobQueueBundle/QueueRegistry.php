<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueueBundle;

use Issei\SimpleJobQueue\ProcessFactoryInterface;
use Issei\SimpleJobQueue\QueueInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class QueueRegistry
{
    private $nameQueueMap = [];
    private $queueProcessFactoryMap;

    public function __construct(iterable $queueAndProcessFactorySets)
    {
        $this->queueProcessFactoryMap = new \SplObjectStorage();

        foreach ($queueAndProcessFactorySets as $name => [$queue, $processFactory]) {
            \assert($queue instanceof QueueInterface);
            \assert(null === $processFactory || $processFactory instanceof ProcessFactoryInterface);

            $this->nameQueueMap[$name] = $queue;
            $this->queueProcessFactoryMap[$queue] = $processFactory;
        }
    }

    /**
     * Returns the queue by name.
     *
     * @param string $name
     *
     * @return QueueInterface
     *
     * @throws \InvalidArgumentException when no queues for the given name.
     */
    public function getQueue(string $name): QueueInterface
    {
        if (!isset($this->nameQueueMap[$name])) {
            throw new \InvalidArgumentException('No queues are registered for the name ' . $name . '.');
        }

        return $this->nameQueueMap[$name];
    }

    /**
     * Returns the process factory corresponding to the given queue, NULL can be returned if no corresponding one exists.
     *
     * @param QueueInterface $queue
     *
     * @return ProcessFactoryInterface|null
     */
    public function getProcessFactoryFor(QueueInterface $queue): ?ProcessFactoryInterface
    {
        return $this->queueProcessFactoryMap[$queue];
    }
}
