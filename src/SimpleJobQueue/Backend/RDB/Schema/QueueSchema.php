<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB\Schema;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class QueueSchema
{
    public $table = 'jobs';
    public $idColumn = 'id';
    public $nameColumn = 'name';
    public $argumentsColumn = 'arguments';
    public $executeAtColumn = 'execute_at';
    public $maxRetriesColumn = 'max_retries';
    public $retryIntervalColumn = 'retry_interval';
}
