<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule\Periodic;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait MinutelyTrait
{
    use InSecondIntervalTrait;

    /**
     * {@inheritdoc}
     */
    protected function getInterval(): int
    {
        return 60;
    }
}
