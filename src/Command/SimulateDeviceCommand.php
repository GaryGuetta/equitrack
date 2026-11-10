<?php

namespace App\Command;

use App\Api\ReadingBatch;
use App\Entity\Device;
use App\Monitoring\ReadingIngestor;
use App\Repository\DeviceRepository;
use App\Repository\ReadingRepository;
use App\Simulation\HorseSimulator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Joue le rôle d'un boîtier : envoie un lot de 5 mesures (10 min), éventuellement avec un scénario d'alerte.
 *
 *   php bin/console app:simulate EQT-TORNADE --scenario=sortie
 *   php bin/console app:simulate EQT-CARAMEL --live
 */
#[AsCommand(name: 'app:simulate', description: 'Simule l\'envoi d\'un lot de mesures par un boîtier.')]
final class SimulateDeviceCommand
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly ReadingRepository $readings,
        private readonly ReadingIngestor $ingestor,
        private readonly HorseSimulator $simulator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Numéro de série du boîtier, ex. EQT-TORNADE')] string $serial,
        #[Option(description: 'Scénario : normal, sortie, roulades, transpiration, agitation')] string $scenario = 'normal',
        #[Option(description: 'Envoie un lot toutes les 10 s, comme un boîtier en accéléré')] bool $live = false,
    ): int {
        if (!\in_array($scenario, HorseSimulator::SCENARIOS, true)) {
            $io->error(\sprintf('Scénario inconnu. Choix : %s.', implode(', ', HorseSimulator::SCENARIOS)));

            return Command::INVALID;
        }

        $device = $this->devices->findOneBy(['serial' => $serial]);
        if (!$device instanceof Device || null === $device->getHorse()) {
            $io->error('Boîtier introuvable ou non associé à un cheval.');

            return Command::FAILURE;
        }

        do {
            $this->sendBatch($io, $device, $scenario);
            if ($live) {
                sleep(10);
            }
        } while ($live);

        return Command::SUCCESS;
    }

    private function sendBatch(SymfonyStyle $io, Device $device, string $scenario): void
    {
        $horse = $device->getHorse();
        $latest = $this->readings->findLatest($horse);
        $now = new \DateTimeImmutable();
        $start = $latest?->hasPosition() ? [$latest->getLatitude(), $latest->getLongitude()] : null;

        // Un lot couvre 10 min ; l'agitation se juge sur l'heure écoulée, on envoie donc une heure entière.
        $span = 'agitation' === $scenario ? '-58 minutes' : '-8 minutes';
        $inputs = $this->simulator->simulate($horse, $now->modify($span), $now, $start, $scenario, batteryStart: $device->getBatteryLevel() ?? 100);
        $alerts = $this->ingestor->ingest($device, new ReadingBatch($inputs));

        $io->writeln(\sprintf('<info>%s</info> %d mesures envoyées pour %s', $now->format('H:i:s'), \count($inputs), $horse->getName()));
        foreach ($alerts as $alert) {
            $io->writeln(\sprintf('  <comment>Alerte</comment> %s : %s', $alert->getType()->label(), $alert->getMessage()));
        }
    }
}
