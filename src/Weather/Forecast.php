<?php

namespace App\Weather;

final readonly class Forecast
{
    public function __construct(
        public float $currentTemperature,
        /** Minimum attendu sur les 12 prochaines heures, °C. */
        public float $minTemperature,
        /** Probabilité de pluie la plus forte sur les 12 prochaines heures, %. */
        public int $maxPrecipitationProbability,
        public float $windSpeed,
    ) {
    }

    public function rainExpected(): bool
    {
        return $this->maxPrecipitationProbability >= 50;
    }
}
