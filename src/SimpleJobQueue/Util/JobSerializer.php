<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Util;

use Doctrine\Instantiator\Instantiator;
use Issei\SimpleJobQueue\Job;
use Issei\SimpleJobQueue\JobId;
use Ramsey\Uuid\Uuid;

/**
 * @internal
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
class JobSerializer
{
    /**
     * @var Instantiator
     */
    private $instantiator;

    /**
     * @var \ReflectionProperty
     */
    private $jobIdPropertyReflector;

    /**
     * @var \ReflectionProperty[]
     */
    private $jobPropertyReflectors;

    public function __construct(Instantiator $instantiator = null)
    {
        $this->instantiator = $instantiator ?: new Instantiator();
    }

    private function ensureReflectorsInitialized(): void
    {
        if ($this->jobIdPropertyReflector) {
            return;
        }

        $jobIdRefl = new \ReflectionClass(JobId::class);
        $this->jobIdPropertyReflector = $jobIdRefl->getProperty('id');
        $this->jobIdPropertyReflector->setAccessible(true);

        $jobRefl = new \ReflectionClass(Job::class);
        foreach (['id', 'name', 'arguments', 'maxRetries', 'retryInterval'] as $propName) {
            $this->jobPropertyReflectors[$propName] = $jobRefl->getProperty($propName);
            $this->jobPropertyReflectors[$propName]->setAccessible(true);
        }
    }

    public function serialize(Job $job): array
    {
        $this->ensureReflectorsInitialized();

        return [
            'id' => $job->getId()->__toString(),
            'name' => $this->jobPropertyReflectors['name']->getValue($job),
            'arguments' => json_encode($this->jobPropertyReflectors['arguments']->getValue($job)),
            'max_retries' => $this->jobPropertyReflectors['maxRetries']->getValue($job),
            'retry_interval' => $this->jobPropertyReflectors['retryInterval']->getValue($job),
        ];
    }

    public function deserialize(array $assoc): Job
    {
        $this->ensureReflectorsInitialized();

        $job = $this->instantiator->instantiate(Job::class);

        $arguments = json_decode($assoc['arguments'], true);
        assert(is_array($arguments));

        $this->jobPropertyReflectors['id']->setValue($job, new JobId(Uuid::fromString($assoc['id'])));
        $this->jobPropertyReflectors['name']->setValue($job, $assoc['name']);
        $this->jobPropertyReflectors['arguments']->setValue($job, $arguments);
        $this->jobPropertyReflectors['maxRetries']->setValue($job, $assoc['max_retries']);
        $this->jobPropertyReflectors['retryInterval']->setValue($job, $assoc['retry_interval']);

        return $job;
    }
}
