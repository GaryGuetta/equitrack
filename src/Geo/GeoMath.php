<?php

namespace App\Geo;

/**
 * Calculs géographiques à l'échelle d'un pré : distances et appartenance à l'enclos (F2, F4).
 * Les points sont des couples [latitude, longitude] en degrés.
 */
final class GeoMath
{
    private const EARTH_RADIUS_M = 6_371_000;

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Longueur d'un trajet en mètres. Les sauts de moins de $noiseMeters sont ignorés :
     * ce sont surtout du bruit GNSS quand le cheval est immobile.
     *
     * @param iterable<array{0: float, 1: float}> $points
     */
    public static function pathLength(iterable $points, float $noiseMeters = 3.0): float
    {
        $total = 0.0;
        $previous = null;
        foreach ($points as $point) {
            if (null !== $previous) {
                $step = self::distance($previous[0], $previous[1], $point[0], $point[1]);
                if ($step < $noiseMeters) {
                    continue;
                }
                $total += $step;
            }
            $previous = $point;
        }

        return $total;
    }

    /**
     * Test du rayon (ray casting) : suffisant pour un enclos de quelques hectares.
     *
     * @param list<array{0: float, 1: float}> $polygon
     */
    public static function containsPoint(array $polygon, float $lat, float $lng): bool
    {
        $inside = false;
        $count = \count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];
            if (($latI > $lat) !== ($latJ > $lat)
                && $lng < ($lngJ - $lngI) * ($lat - $latI) / ($latJ - $latI) + $lngI) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Distance en mètres entre un point et le bord le plus proche du polygone.
     *
     * @param list<array{0: float, 1: float}> $polygon
     */
    public static function distanceToEdge(array $polygon, float $lat, float $lng): float
    {
        // Projection équirectangulaire locale : précise au mètre près sur un pré.
        $kx = cos(deg2rad($lat)) * M_PI / 180 * self::EARTH_RADIUS_M;
        $ky = M_PI / 180 * self::EARTH_RADIUS_M;
        $min = \INF;
        $count = \count($polygon);
        for ($i = 0; $i < $count; ++$i) {
            [$aLat, $aLng] = $polygon[$i];
            [$bLat, $bLng] = $polygon[($i + 1) % $count];
            $ax = ($aLng - $lng) * $kx;
            $ay = ($aLat - $lat) * $ky;
            $bx = ($bLng - $lng) * $kx;
            $by = ($bLat - $lat) * $ky;
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $lengthSquared = $dx * $dx + $dy * $dy;
            $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;
            $min = min($min, hypot($ax + $t * $dx, $ay + $t * $dy));
        }

        return $min;
    }

    /**
     * @param list<array{0: float, 1: float}> $polygon
     *
     * @return array{0: float, 1: float}|null
     */
    public static function centroid(array $polygon): ?array
    {
        if ([] === $polygon) {
            return null;
        }

        return [
            array_sum(array_column($polygon, 0)) / \count($polygon),
            array_sum(array_column($polygon, 1)) / \count($polygon),
        ];
    }
}
