<?php

namespace App\Monitoring;

use App\Entity\Alert;
use App\Entity\Horse;
use App\Entity\Reading;
use App\Enum\AlertType;
use App\Enum\Gait;
use App\Geo\GeoMath;
use App\Repository\AlertRepository;
use App\Repository\ReadingRepository;
use App\Util\Fr;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique les règles d'alerte du cahier des charges sur un lot de mesures fraîchement enregistré.
 *
 * Les seuils sont des hypothèses de départ : ils sont à recalibrer avec les mesures des essais.
 */
class AlertEvaluator
{
    /** Au-delà, l'air sous la couverture indique un cheval qui a trop chaud (F9). */
    public const HOT_THRESHOLD = 35.0;
    /** En dessous, le cheval perd de la chaleur malgré la couverture (F9). */
    public const COLD_THRESHOLD = 10.0;
    /** Humidité relative signalant une couverture mouillée ou une transpiration (F9). */
    public const WET_THRESHOLD = 85.0;
    public const LOW_BATTERY = 15;
    /** Nombre de roulades sur 30 min qui doit alerter (signe possible de colique, F8). */
    public const ROLLS_THRESHOLD = 3;
    public const LYING_MINUTES = 90;
    /** Écart à la moyenne habituelle, en écarts types, pour parler d'agitation (F8). */
    public const AGITATION_Z = 3.0;
    /** Jours de mesures nécessaires avant d'alerter sur l'activité (apprentissage, F8). */
    public const BASELINE_MIN_DAYS = 5;
    public const BASELINE_DAYS = 14;
    /** Une alerte non traitée du même type n'est pas répétée pendant ce délai. */
    private const DEDUP_WINDOW = '-2 hours';

    public function __construct(
        private readonly ReadingRepository $readings,
        private readonly AlertRepository $alerts,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Les mesures doivent déjà être enregistrées en base. Les alertes créées sont persistées, pas flushées.
     *
     * @param list<Reading> $batch triées par date croissante
     *
     * @return list<Alert>
     */
    public function evaluate(Horse $horse, array $batch): array
    {
        if ([] === $batch) {
            return [];
        }

        $raised = [];
        $raise = function (AlertType $type, string $message, \DateTimeImmutable $at) use ($horse, &$raised): void {
            if (isset($raised[$type->value])
                || $this->alerts->hasOpenSince($horse, $type, $at->modify(self::DEDUP_WINDOW))) {
                return;
            }
            $alert = new Alert($horse, $type, $message, $at);
            $this->em->persist($alert);
            $raised[$type->value] = $alert;
        };

        $name = $horse->getName();
        foreach ($batch as $reading) {
            $at = $reading->getRecordedAt();

            if ($horse->hasPaddock() && $reading->hasPosition()
                && !GeoMath::containsPoint($horse->getPaddock(), $reading->getLatitude(), $reading->getLongitude())) {
                $meters = GeoMath::distanceToEdge($horse->getPaddock(), $reading->getLatitude(), $reading->getLongitude());
                $raise(AlertType::GeofenceExit, \sprintf('%s est sorti de son enclos (à %d m de la clôture).', $name, round($meters)), $at);
            }

            $temperature = $reading->getTemperature();
            if (null !== $temperature && $temperature >= self::HOT_THRESHOLD) {
                $raise(AlertType::TooHot, \sprintf('%s °C sous la couverture de %s : il a probablement trop chaud.', Fr::number($temperature), $name), $at);
            } elseif (null !== $temperature && $temperature <= self::COLD_THRESHOLD) {
                $raise(AlertType::TooCold, \sprintf('Seulement %s °C sous la couverture de %s.', Fr::number($temperature), $name), $at);
            }

            $humidity = $reading->getHumidity();
            if (null !== $humidity && $humidity >= self::WET_THRESHOLD) {
                $raise(AlertType::WetBlanket, \sprintf('%d %% d\'humidité sous la couverture de %s : couverture mouillée ou cheval qui transpire.', round($humidity), $name), $at);
            }

            $battery = $reading->getBattery();
            if (null !== $battery && $battery <= self::LOW_BATTERY) {
                $raise(AlertType::LowBattery, \sprintf('Batterie du boîtier de %s à %d %% : pensez à le recharger.', $name, $battery), $at);
            }
        }

        $latest = $batch[array_key_last($batch)];
        $now = $latest->getRecordedAt();

        $rolls = $this->readings->countRollsSince($horse, $now->modify('-30 minutes'));
        if ($rolls >= self::ROLLS_THRESHOLD) {
            $raise(AlertType::RepeatedRolling, \sprintf('%s s\'est roulé %d fois en 30 min. Surveillez un risque de colique et appelez le vétérinaire en cas de doute.', $name, $rolls), $now);
        }

        $this->checkProlongedLying($horse, $now, $raise);
        $this->checkActivity($horse, $now, $raise);

        return array_values($raised);
    }

    private function checkProlongedLying(Horse $horse, \DateTimeImmutable $now, \Closure $raise): void
    {
        $recent = $this->readings->findBetween($horse, $now->modify('-'.self::LYING_MINUTES.' minutes'));
        if (\count($recent) < 3) {
            return;
        }
        $coveredMinutes = ($now->getTimestamp() - $recent[0]->getRecordedAt()->getTimestamp()) / 60;
        $allLying = array_all($recent, static fn (Reading $r) => Gait::Lying === $r->getGait());

        if ($allLying && $coveredMinutes >= self::LYING_MINUTES - 10) {
            $raise(AlertType::ProlongedLying, \sprintf('%s est couché depuis plus de %d min.', $horse->getName(), self::LYING_MINUTES - 10), $now);
        }
    }

    /**
     * Compare l'activité de la dernière heure à celle des mêmes heures des jours précédents (F8).
     */
    private function checkActivity(Horse $horse, \DateTimeImmutable $now, \Closure $raise): void
    {
        $lastHour = array_filter(
            array_map(static fn (Reading $r) => $r->getActivity(), $this->readings->findBetween($horse, $now->modify('-60 minutes'))),
            static fn (?float $a) => null !== $a,
        );
        if (\count($lastHour) < 10) {
            return;
        }

        $baseline = ActivityBaseline::fromDailySamples(
            $this->readings->findActivityInDailyWindow($horse, $now, 60, self::BASELINE_DAYS),
        );
        if ($baseline->days < self::BASELINE_MIN_DAYS) {
            return;
        }

        $current = array_sum($lastHour) / \count($lastHour);
        $z = $baseline->zScore($current);

        if ($z >= self::AGITATION_Z) {
            $raise(AlertType::Agitation, \sprintf('%s est beaucoup plus agité que d\'habitude à cette heure (activité %d contre %d en moyenne).', $horse->getName(), round($current), round($baseline->mean)), $now);
        } elseif ($baseline->mean >= 12 && $current < $baseline->mean * 0.25 && $z <= -2) {
            $raise(AlertType::Inactivity, \sprintf('%s bouge beaucoup moins que d\'habitude à cette heure (activité %d contre %d en moyenne).', $horse->getName(), round($current), round($baseline->mean)), $now);
        }
    }
}
