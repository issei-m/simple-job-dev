<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue\Backend\RDB;

use Doctrine\DBAL\Connection;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait BaseTrait
{
    /**
     * @var Connection
     */
    private $conn;
}
