<?php

namespace App\Tests\Unit;

use App\Geo\GeoMath;
use PHPUnit\Framework\TestCase;

final class GeoMathTest extends TestCase
{
    private const SQUARE = [[44.0220, 1.3520], [44.0230, 1.3520], [44.0230, 1.3534], [44.0220, 1.3534]];

    public function testOneDegreeOfLatitudeIsAbout111Km(): void
    {
        self::assertEqualsWithDelta(111_195, GeoMath::distance(44.0, 1.0, 45.0, 1.0), 50);
    }

    public function testPointInsideAndOutsidePaddock(): void
    {
        self::assertTrue(GeoMath::containsPoint(self::SQUARE, 44.0225, 1.3527));
        self::assertFalse(GeoMath::containsPoint(self::SQUARE, 44.0235, 1.3527));
    }

    public function testDistanceToEdge(): void
    {
        // 0,0005° de latitude au-dessus du bord nord : environ 55 m.
        self::assertEqualsWithDelta(55.6, GeoMath::distanceToEdge(self::SQUARE, 44.0235, 1.3527), 1);
    }

    public function testPathLengthIgnoresGpsJitter(): void
    {
        $jitter = [[44.0225, 1.3527], [44.02251, 1.35271], [44.02249, 1.35269]];
        self::assertSame(0.0, GeoMath::pathLength($jitter));

        $walk = [[44.0220, 1.3527], [44.0230, 1.3527]];
        self::assertEqualsWithDelta(111.2, GeoMath::pathLength($walk), 0.5);
    }

    public function testCentroid(): void
    {
        self::assertEqualsWithDelta([44.0225, 1.3527], GeoMath::centroid(self::SQUARE), 1e-9);
        self::assertNull(GeoMath::centroid([]));
    }
}
