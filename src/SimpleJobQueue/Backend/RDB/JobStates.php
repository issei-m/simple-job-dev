<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class JobStates
{
    const STATE_RUNNING = 'running';
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';

    private function __construct()
    {
    }
}
