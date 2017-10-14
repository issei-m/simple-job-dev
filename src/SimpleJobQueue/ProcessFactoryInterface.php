<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

use Symfony\Component\Process\Process;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
interface ProcessFactoryInterface
{
    /**
     * Returns the process to run the given command with arguments.
     *
     * @param string   $command
     * @param iterable $arguments
     *
     * @return Process
     */
    public function createProcess(string $command, iterable $arguments): Process;
}
