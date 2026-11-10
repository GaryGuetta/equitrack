<?php

namespace App\Monitoring;

/**
 * Comportement habituel du cheval sur une fenêtre horaire : moyenne et dispersion des moyennes journalières.
 */
final readonly class ActivityBaseline
{
    /** Plancher de l'écart type : évite qu'un cheval très régulier déclenche des alertes pour rien. */
    private const MIN_STD_DEV = 5.0;

    private function __construct(
        public float $mean,
        public float $stdDev,
        public int $days,
    ) {
    }

    /** @param array<string, list<float>> $samplesByDay */
    public static function fromDailySamples(array $samplesByDay): self
    {
        $dailyMeans = array_values(array_map(
            static fn (array $samples) => array_sum($samples) / \count($samples),
            array_filter($samplesByDay, static fn (array $samples) => [] !== $samples),
        ));
        $days = \count($dailyMeans);
        if (0 === $days) {
            return new self(0.0, self::MIN_STD_DEV, 0);
        }

        $mean = array_sum($dailyMeans) / $days;
        $variance = array_sum(array_map(static fn (float $m) => ($m - $mean) ** 2, $dailyMeans)) / $days;

        return new self($mean, max(self::MIN_STD_DEV, sqrt($variance)), $days);
    }

    public function zScore(float $value): float
    {
        return ($value - $this->mean) / $this->stdDev;
    }
}
