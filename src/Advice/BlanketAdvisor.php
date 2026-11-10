<?php

namespace App\Advice;

use App\Entity\Horse;
use App\Entity\Reading;
use App\Enum\BlanketWeight;
use App\Enum\Clip;
use App\Util\Fr;
use App\Weather\Forecast;

/**
 * Conseil de couverture (F7) : grille température × tonte, ajustée par le profil du cheval,
 * la pluie prévue et le microclimat mesuré sous la couverture actuelle.
 *
 * La grille reprend les repères courants des fabricants de couvertures ; elle doit être
 * validée par un professionnel sur des cas tests (critère de validation de F7).
 */
class BlanketAdvisor
{
    /**
     * Seuil bas de chaque tranche de température, de la plus chaude à la plus froide.
     */
    private const BANDS = [15, 10, 5, 0, -5, \PHP_INT_MIN];

    /**
     * @var array<string, list<BlanketWeight>> une entrée par tranche de BANDS
     */
    private const GRID = [
        Clip::None->value => [BlanketWeight::None, BlanketWeight::None, BlanketWeight::None, BlanketWeight::Light, BlanketWeight::Medium, BlanketWeight::Heavy],
        Clip::Partial->value => [BlanketWeight::None, BlanketWeight::RainSheet, BlanketWeight::Light, BlanketWeight::Medium, BlanketWeight::Heavy, BlanketWeight::ExtraHeavy],
        Clip::Full->value => [BlanketWeight::None, BlanketWeight::Light, BlanketWeight::Medium, BlanketWeight::Heavy, BlanketWeight::ExtraHeavy, BlanketWeight::ExtraHeavy],
    ];

    /** Sous la couverture, au-delà : le cheval a trop chaud. */
    private const MICROCLIMATE_HOT = 32.0;
    /** Sous la couverture, au-delà : transpiration probable. */
    private const MICROCLIMATE_HUMID = 80.0;
    /** Sous la couverture, en dessous : la couverture ne suffit pas. */
    private const MICROCLIMATE_COLD = 18.0;
    /** Une mesure plus ancienne ne reflète plus la situation actuelle. */
    private const READING_MAX_AGE = '-1 hour';

    public function advise(Horse $horse, ?Forecast $forecast, ?Reading $latest): ?BlanketAdvice
    {
        $isFresh = null !== $latest && $latest->getRecordedAt() >= new \DateTimeImmutable(self::READING_MAX_AGE);

        if (null !== $forecast) {
            $temperature = $forecast->minTemperature;
            $source = 'minimum prévu sur 12 h';
        } elseif ($isFresh && null !== $latest->getOutsideTemperature()) {
            $temperature = $latest->getOutsideTemperature();
            $source = 'mesurée par le boîtier';
        } else {
            return null;
        }

        $reasons = [];
        $rank = self::GRID[$horse->getClip()->value][$this->band($temperature)]->rank();
        $reasons[] = \sprintf('%s à %.0f °C (%s).', $horse->getClip()->label(), $temperature, $source);

        if ($horse->isColdSensitive() || ($horse->getAge() ?? 0) >= 20) {
            ++$rank;
            $reasons[] = 'Cheval frileux ou âgé : un cran de plus.';
        }

        if (null !== $forecast && $forecast->rainExpected() && $temperature < 15) {
            if ($rank < BlanketWeight::RainSheet->rank() && !$horse->hasShelter()) {
                $rank = BlanketWeight::RainSheet->rank();
                $reasons[] = \sprintf('Pluie probable (%d %%) et pas d\'abri : un imperméable garde le poil sec, donc isolant.', $forecast->maxPrecipitationProbability);
            } elseif ($rank < BlanketWeight::RainSheet->rank()) {
                $reasons[] = 'Pluie probable, mais le cheval dispose d\'un abri.';
            }
        }

        // Le microclimat mesuré corrige la grille : c'est l'apport propre d'Equitrack.
        $current = $horse->getCurrentBlanket();
        if ($isFresh && BlanketWeight::None !== $current && null !== $latest->getTemperature()) {
            $underTemp = $latest->getTemperature();
            $underHumidity = $latest->getHumidity() ?? 0.0;
            if ($underTemp >= self::MICROCLIMATE_HOT || $underHumidity >= self::MICROCLIMATE_HUMID) {
                $rank = min($rank, $current->rank() - 1);
                $reasons[] = \sprintf('Sous la couverture actuelle : %s °C et %d %% d\'humidité. Le cheval a trop chaud ou transpire.', Fr::number($underTemp), round($underHumidity));
            } elseif ($underTemp < self::MICROCLIMATE_COLD) {
                $rank = max($rank, $current->rank() + 1);
                $reasons[] = \sprintf('Sous la couverture actuelle, il ne fait que %s °C : elle ne suffit pas.', Fr::number($underTemp));
            }
        }

        return new BlanketAdvice(BlanketWeight::fromRank($rank), $current, $temperature, $source, $reasons);
    }

    private function band(float $temperature): int
    {
        foreach (self::BANDS as $index => $floor) {
            if ($temperature >= $floor) {
                return $index;
            }
        }

        return \count(self::BANDS) - 1;
    }
}
