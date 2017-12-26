<?php

declare(strict_types=1);

namespace Tests\Issei\SimpleJobSchedule\Periodic;

use Issei\SimpleJobSchedule\Periodic\InSecondIntervalTrait;
use PHPUnit\Framework\TestCase;

class InSecondIntervalTraitTest extends TestCase
{
    public function testShouldRun()
    {
        $schedule = new class {
            use InSecondIntervalTrait;

            protected function getInterval(): int
            {
                return 30;
            }
        };

        self::assertFalse($schedule->shouldRun(new \DateTimeImmutable('-29 sec')));
        self::assertTrue($schedule->shouldRun(new \DateTimeImmutable('-30 sec')));
    }
}
