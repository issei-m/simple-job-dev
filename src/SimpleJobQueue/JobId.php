<?php

declare(strict_types=1);

namespace Issei\SimpleJobQueue;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
final class JobId
{
    private $id;

    public function __construct(UuidInterface $uuid = null)
    {
        $this->id = ($uuid ?: Uuid::getFactory()->uuid4())->toString();
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
