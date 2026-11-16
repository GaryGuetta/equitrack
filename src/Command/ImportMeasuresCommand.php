<?php

namespace App\Command;

use App\Monitoring\MeasureImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les mesures écrites par les boîtiers dans Supabase. Le site le fait aussi tout seul pendant la navigation.
 *
 *   php bin/console app:mesures:import
 *   php bin/console app:mesures:import --live
 */
#[AsCommand(name: 'app:mesures:import', description: 'Importe les nouvelles lignes de la table Supabase « mesures ».')]
final class ImportMeasuresCommand
{
    public function __construct(private readonly MeasureImporter $importer)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Recommence toutes les 30 s')] bool $live = false,
    ): int {
        if (!$this->importer->isAvailable()) {
            $io->error('Table « mesures » introuvable : le site n\'est pas branché sur la base Supabase (DATABASE_URL dans .env.local).');

            return Command::FAILURE;
        }

        do {
            $report = $this->importer->import();
            $io->writeln(\sprintf('[%s] %d mesure(s) importée(s), %d alerte(s).', date('H:i:s'), $report['readings'], $report['alerts']));
            if ($live) {
                sleep(30);
            }
        } while ($live);

        return Command::SUCCESS;
    }
}
