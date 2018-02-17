<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class JobStates
{
    public const STATE_RUNNING = 'running';
    public const STATE_FINISHED = 'finished';
    public const STATE_FAILED = 'failed';

    private function __construct()
    {
    }
}
