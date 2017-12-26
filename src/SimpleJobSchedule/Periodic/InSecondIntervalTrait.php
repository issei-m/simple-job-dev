<?php

declare(strict_types=1);

namespace Issei\SimpleJobSchedule\Periodic;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 */
trait InSecondIntervalTrait
{
    /**
     * {@inheritdoc}
     */
    public function shouldRun(\DateTimeInterface $lastRanAt): bool
    {
        return \time() - $lastRanAt->getTimestamp() >= $this->getInterval();
    }

    /**
     * Returns the interval of periodic job in a second.
     *
     * @return int
     */
    abstract protected function getInterval(): int;
}
