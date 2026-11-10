<?php

namespace App\Monitoring;

use App\Entity\Reading;
use App\Enum\Gait;
use App\Geo\GeoMath;

/**
 * Synthèse d'une série de mesures : distance, temps passé à chaque allure, extrêmes de température.
 */
final readonly class DaySummary
{
    /** Durée maximale attribuée à une mesure : au-delà, on considère qu'il manque des données. */
    private const MAX_STEP_SECONDS = 600;
    private const DEFAULT_STEP_SECONDS = 120;

    /**
     * @param array<string, int> $minutesByGait minutes par valeur de Gait, dans l'ordre de l'énumération
     */
    private function __construct(
        public float $distanceMeters,
        public array $minutesByGait,
        public int $rolls,
        public ?float $temperatureMin,
        public ?float $temperatureMax,
        public ?float $humidityAverage,
        public int $readingCount,
    ) {
    }

    /** @param list<Reading> $readings triées par date croissante */
    public static function fromReadings(array $readings): self
    {
        $seconds = array_fill_keys(array_map(static fn (Gait $g) => $g->value, Gait::cases()), 0);
        $rolls = 0;
        $temperatures = [];
        $humidities = [];
        $points = [];

        foreach ($readings as $i => $reading) {
            $next = $readings[$i + 1] ?? null;
            $step = null === $next
                ? self::DEFAULT_STEP_SECONDS
                : min(self::MAX_STEP_SECONDS, $next->getRecordedAt()->getTimestamp() - $reading->getRecordedAt()->getTimestamp());

            if (null !== $gait = $reading->getGait()) {
                $seconds[$gait->value] += $step;
            }
            $rolls += $reading->getRolls();
            if (null !== $reading->getTemperature()) {
                $temperatures[] = $reading->getTemperature();
            }
            if (null !== $reading->getHumidity()) {
                $humidities[] = $reading->getHumidity();
            }
            if ($reading->hasPosition()) {
                $points[] = [$reading->getLatitude(), $reading->getLongitude()];
            }
        }

        return new self(
            GeoMath::pathLength($points),
            array_map(static fn (int $s) => intdiv($s, 60), $seconds),
            $rolls,
            [] === $temperatures ? null : min($temperatures),
            [] === $temperatures ? null : max($temperatures),
            [] === $humidities ? null : array_sum($humidities) / \count($humidities),
            \count($readings),
        );
    }

    public function movingMinutes(): int
    {
        return $this->minutesByGait[Gait::Walk->value] + $this->minutesByGait[Gait::Trot->value] + $this->minutesByGait[Gait::Canter->value];
    }
}
