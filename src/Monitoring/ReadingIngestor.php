<?php

namespace App\Monitoring;

use App\Api\ReadingBatch;
use App\Api\ReadingInput;
use App\Entity\Alert;
use App\Entity\Device;
use App\Entity\Horse;
use App\Entity\Reading;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre un lot de mesures envoyé par un boîtier, puis évalue les alertes (F5, F6).
 */
class ReadingIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlertEvaluator $evaluator,
        private readonly AlertNotifier $notifier,
    ) {
    }

    /**
     * @return list<Alert> alertes déclenchées par ce lot
     */
    public function ingest(Device $device, ReadingBatch $batch): array
    {
        $horse = $device->getHorse() ?? throw new \LogicException('Ce boîtier n\'est associé à aucun cheval.');

        $inputs = $batch->readings;
        usort($inputs, static fn (ReadingInput $a, ReadingInput $b) => $a->recordedAt <=> $b->recordedAt);

        $readings = [];
        foreach ($inputs as $input) {
            $reading = $this->toReading($horse, $input);
            $this->em->persist($reading);
            $readings[] = $reading;
        }

        $device->markSeen(new \DateTimeImmutable(), $inputs[array_key_last($inputs)]->battery);
        $this->em->flush();

        $alerts = $this->evaluator->evaluate($horse, $readings);
        $this->em->flush();

        foreach ($alerts as $alert) {
            $this->notifier->notify($alert);
        }

        return $alerts;
    }

    public function toReading(Horse $horse, ReadingInput $input): Reading
    {
        // Les dates sont stockées sans fuseau : on les ramène toutes au fuseau de l'application.
        $recordedAt = $input->recordedAt->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return (new Reading($horse, $recordedAt))
            ->setPosition($input->latitude, $input->longitude)
            ->setTemperature($input->temperature)
            ->setHumidity($input->humidity)
            ->setOutsideTemperature($input->outsideTemperature)
            ->setActivity($input->activity)
            ->setGait($input->gait)
            ->setRolls($input->rolls)
            ->setHeartRate($input->heartRate)
            ->setBattery($input->battery);
    }
}
