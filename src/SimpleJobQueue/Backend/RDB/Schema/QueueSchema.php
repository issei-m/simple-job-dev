<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB\Schema;

/**
 * @property-read string $table
 * @property-read string $idColumn
 * @property-read string $nameColumn
 * @property-read string $argumentsColumn
 * @property-read string $queueColumn
 * @property-read string $executeAtColumn
 * @property-read string $maxRetriesColumn
 * @property-read string $retriesColumn
 *
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class QueueSchema
{
    use ReadOnlyPropertiesTrait;

    private const DEFAULT_PROPERTIES = [
        'table' => 'jobs',
        'idColumn' => 'id',
        'nameColumn' => 'name',
        'argumentsColumn' => 'arguments',
        'queueColumn' => 'queue',
        'executeAtColumn' => 'execute_at',
        'maxRetriesColumn' => 'max_retries',
        'retriesColumn' => 'retries',
    ];
}
