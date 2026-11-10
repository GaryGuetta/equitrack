<?php

namespace App\Tests\Unit;

use App\Monitoring\ActivityBaseline;
use PHPUnit\Framework\TestCase;

final class ActivityBaselineTest extends TestCase
{
    public function testMeanOfDailyMeans(): void
    {
        $baseline = ActivityBaseline::fromDailySamples([
            '2026-11-01' => [10, 30],   // moyenne 20
            '2026-11-02' => [20],       // moyenne 20
            '2026-11-03' => [26, 26],   // moyenne 26
        ]);

        self::assertSame(3, $baseline->days);
        self::assertEqualsWithDelta(22.0, $baseline->mean, 1e-9);
    }

    public function testStandardDeviationHasAFloor(): void
    {
        $baseline = ActivityBaseline::fromDailySamples(['a' => [20], 'b' => [20], 'c' => [20]]);

        self::assertSame(5.0, $baseline->stdDev);
        self::assertEqualsWithDelta(3.0, $baseline->zScore(35), 1e-9);
    }

    public function testEmptyHistory(): void
    {
        self::assertSame(0, ActivityBaseline::fromDailySamples([])->days);
    }
}
