<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB\Schema;

/**
 * @property-read string $table
 * @property-read string $jobIdColumn
 * @property-read string $stateColumn
 * @property-read string $workerNameColumn
 * @property-read string $startedAtColumn
 * @property-read string $finishedAtColumn
 * @property-read string $exitCodeColumn
 * @property-read string $stdoutColumn
 * @property-read string $stderrColumn
 * @property-read string $retryToColumn
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class ReporterSchema
{
    use ReadOnlyPropertiesTrait;

    private const DEFAULT_PROPERTIES = [
        'table' => 'job_reports',
        'jobIdColumn' => 'job_id',
        'stateColumn' => 'state',
        'workerNameColumn' => 'worker_name',
        'startedAtColumn' => 'started_at',
        'finishedAtColumn' => 'finished_at',
        'exitCodeColumn' => 'exit_code',
        'stdoutColumn' => 'stdout',
        'stderrColumn' => 'stderr',
        'retryToColumn' => 'retry_to',
    ];
}
