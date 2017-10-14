<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB\Schema;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class ReporterSchema
{
    public $table = 'job_reports';
    public $jobIdColumn = 'job_id';
    public $stateColumn = 'state';
    public $workerNameColumn = 'worker_name';
    public $startedAtColumn = 'started_at';
    public $finishedAtColumn = 'finished_at';
    public $exitCodeColumn = 'exit_code';
    public $stdoutColumn = 'stdout';
    public $stderrColumn = 'stderr';
    public $retryToColumn = 'retry_to';
}
