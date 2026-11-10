<?php

namespace App\Simulation;

use App\Api\ReadingInput;
use App\Entity\Horse;
use App\Enum\Gait;
use App\Geo\GeoMath;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Génère des mesures plausibles pour un cheval au pré, en attendant les données du vrai boîtier.
 * Sert aux données de démonstration et à rejouer des scénarios d'alerte en soutenance.
 */
class HorseSimulator
{
    public const SCENARIOS = ['normal', 'sortie', 'roulades', 'transpiration', 'agitation'];

    /** Mètres parcourus en 2 min selon l'allure (pâturage compris dans le pas). */
    private const STEP_METERS = [
        'couche' => 0, 'repos' => 1, 'pas' => 10, 'trot' => 45, 'galop' => 80,
    ];
    private const ACTIVITY = [
        'couche' => [1, 1], 'repos' => [5, 2.5], 'pas' => [25, 7], 'trot' => [60, 7], 'galop' => [85, 6],
    ];

    private Randomizer $random;
    /** Cap actuel en radians : il dérive doucement, comme un cheval qui broute en avançant. */
    private float $heading = 0.0;

    public function __construct(?int $seed = null)
    {
        $this->random = new Randomizer(null === $seed ? null : new Mt19937($seed));
    }

    /**
     * @param array{0: float, 1: float}|null $start position de départ, centre de l'enclos par défaut
     *
     * @return list<ReadingInput>
     */
    public function simulate(
        Horse $horse,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?array $start = null,
        string $scenario = 'normal',
        int $stepSeconds = 120,
        int $batteryStart = 100,
    ): array {
        $paddock = $horse->getPaddock();
        $position = $start ?? GeoMath::centroid($paddock) ?? [44.0221, 1.3529];
        $gait = Gait::Rest;
        $dayOffsets = [];
        $total = max(1, intdiv($to->getTimestamp() - $from->getTimestamp(), $stepSeconds));
        $readings = [];

        for ($i = 0, $t = $from; $t <= $to; ++$i, $t = $t->modify("+$stepSeconds seconds")) {
            $hour = (int) $t->format('G') + (int) $t->format('i') / 60;
            $day = $t->format('Y-m-d');
            $dayOffsets[$day] ??= $this->gauss(0, 2.5);

            $gait = $this->nextGait($gait, $hour);
            $rolls = 0;
            // L'agitation se juge sur une heure : elle couvre tout le lot, les autres scénarios les 5 dernières mesures.
            $isLast = $i >= $total - 4 || 'agitation' === $scenario;

            // Les scénarios ne touchent que les dernières mesures du lot.
            if ($isLast && 'roulades' === $scenario) {
                $gait = Gait::Lying;
                $rolls = 1;
            } elseif ($isLast && 'agitation' === $scenario) {
                $gait = $this->random->getInt(0, 1) ? Gait::Canter : Gait::Trot;
            } elseif (Gait::Lying === $gait && $this->random->nextFloat() < 0.003) {
                $rolls = 1;
            }

            $position = $this->move($position, $gait, $paddock);
            if ($isLast && 'sortie' === $scenario && [] !== $paddock) {
                $position = $this->outside($paddock, 25 + 15 * max(0, $i - $total + 4));
            }

            $outside = 7 + 5 * sin(($hour - 9) / 24 * 2 * M_PI) + $dayOffsets[$day] + $this->gauss(0, 0.3);
            [$activityMean, $activitySd] = self::ACTIVITY[$gait->value];
            $activity = $this->clamp($this->gauss($activityMean, $activitySd), 0, 100);
            $under = 26 + 0.3 * ($outside - 7) + $activity * 0.04 + $this->gauss(0, 0.4);
            $humidity = 56 + $activity * 0.12 + $this->gauss(0, 3);
            if ($isLast && 'transpiration' === $scenario) {
                $under += 8;
                $humidity = 88 + $this->gauss(0, 2);
            }

            $readings[] = new ReadingInput(
                recordedAt: $t,
                latitude: round($position[0] + $this->gauss(0, 0.00001), 6),
                longitude: round($position[1] + $this->gauss(0, 0.00001), 6),
                temperature: round($under, 1),
                humidity: round($this->clamp($humidity, 20, 100), 1),
                outsideTemperature: round($outside, 1),
                activity: round($activity, 1),
                gait: $gait,
                rolls: $rolls,
                heartRate: \in_array($gait, [Gait::Rest, Gait::Lying], true) ? (int) round($this->gauss(36, 3)) : null,
                battery: (int) max(5, round($batteryStart - $i * $stepSeconds / (8 * 86400) * 100)),
            );
        }

        return $readings;
    }

    private function nextGait(Gait $previous, float $hour): Gait
    {
        // Les allures durent : on garde souvent la précédente.
        $stickiness = Gait::Lying === $previous ? 0.85 : 0.6;
        if ($this->random->nextFloat() < $stickiness) {
            return $previous;
        }

        $weights = match (true) {
            $hour >= 22 || $hour < 6 => ['couche' => 22, 'repos' => 55, 'pas' => 22, 'trot' => 1, 'galop' => 0],
            ($hour >= 7 && $hour < 9) || ($hour >= 17 && $hour < 19) => ['couche' => 0, 'repos' => 22, 'pas' => 60, 'trot' => 13, 'galop' => 5],
            default => ['couche' => 2, 'repos' => 30, 'pas' => 61, 'trot' => 6, 'galop' => 1],
        };

        $pick = $this->random->getInt(1, array_sum($weights));
        foreach ($weights as $gait => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return Gait::from($gait);
            }
        }

        return Gait::Rest;
    }

    /**
     * @param array{0: float, 1: float}       $position
     * @param list<array{0: float, 1: float}> $paddock
     *
     * @return array{0: float, 1: float}
     */
    private function move(array $position, Gait $gait, array $paddock): array
    {
        $meters = self::STEP_METERS[$gait->value] * (0.5 + $this->random->nextFloat());
        if (0.0 === $meters) {
            return $position;
        }

        for ($attempt = 0; $attempt < 12; ++$attempt) {
            // Petite déviation d'abord ; si la clôture bloque, demi-tours de plus en plus francs.
            $heading = $this->heading + $this->gauss(0, 0.5 + $attempt * 0.5);
            $candidate = [
                $position[0] + $meters * cos($heading) / 111_320,
                $position[1] + $meters * sin($heading) / (111_320 * cos(deg2rad($position[0]))),
            ];
            if ([] === $paddock || GeoMath::containsPoint($paddock, $candidate[0], $candidate[1])) {
                $this->heading = $heading;

                return $candidate;
            }
        }

        return $position;
    }

    /**
     * Point situé à $meters au-delà du premier sommet de l'enclos, vers l'extérieur.
     *
     * @param list<array{0: float, 1: float}> $paddock
     *
     * @return array{0: float, 1: float}
     */
    private function outside(array $paddock, float $meters): array
    {
        [$cLat, $cLng] = GeoMath::centroid($paddock);
        [$vLat, $vLng] = $paddock[0];
        $length = GeoMath::distance($cLat, $cLng, $vLat, $vLng);
        $ratio = ($length + $meters) / $length;

        return [$cLat + ($vLat - $cLat) * $ratio, $cLng + ($vLng - $cLng) * $ratio];
    }

    private function gauss(float $mean, float $sd): float
    {
        $u = 1 - $this->random->nextFloat();
        $v = $this->random->nextFloat();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
