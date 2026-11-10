<?php

namespace App\Monitoring;

use App\Entity\Reading;

/**
 * Agrège les mesures par tranches régulières pour les graphiques : 7 jours à 2 min feraient 5 000 points.
 */
final class TimeSeries
{
    /**
     * @param list<Reading> $readings triées par date croissante
     *
     * @return array{labels: list<string>, temperature: list<?float>, humidity: list<?float>, outside: list<?float>, activity: list<?float>}
     */
    public static function bucket(array $readings, int $bucketMinutes): array
    {
        $buckets = [];
        $size = $bucketMinutes * 60;
        foreach ($readings as $reading) {
            $key = intdiv($reading->getRecordedAt()->getTimestamp(), $size) * $size;
            $buckets[$key][] = $reading;
        }

        $series = ['labels' => [], 'temperature' => [], 'humidity' => [], 'outside' => [], 'activity' => []];
        foreach ($buckets as $timestamp => $group) {
            $series['labels'][] = date(\DATE_ATOM, $timestamp);
            $series['temperature'][] = self::average($group, static fn (Reading $r) => $r->getTemperature());
            $series['humidity'][] = self::average($group, static fn (Reading $r) => $r->getHumidity());
            $series['outside'][] = self::average($group, static fn (Reading $r) => $r->getOutsideTemperature());
            $series['activity'][] = self::average($group, static fn (Reading $r) => $r->getActivity());
        }

        return $series;
    }

    /**
     * @param list<Reading>                 $group
     * @param \Closure(Reading): ?float     $value
     */
    private static function average(array $group, \Closure $value): ?float
    {
        $values = array_filter(array_map($value, $group), static fn (?float $v) => null !== $v);

        return [] === $values ? null : round(array_sum($values) / \count($values), 1);
    }
}
