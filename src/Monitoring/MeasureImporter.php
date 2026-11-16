<?php

namespace App\Monitoring;

use App\Api\ReadingInput;
use App\Entity\Device;
use App\Entity\Reading;
use App\Enum\Gait;
use App\Repository\DeviceRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Importe les lignes que les boîtiers écrivent directement dans la table Supabase « mesures ».
 *
 * Le lien se fait par la colonne horse_id de Supabase, égale au numéro du boîtier sur le site.
 * Chaque ligne passe par le même traitement qu'un envoi à l'API : enclos, roulades, anomalies, alertes.
 * Les valeurs hors plage sont ignorées plutôt que de bloquer la ligne, qui serait sinon retentée sans fin.
 */
class MeasureImporter
{
    /** « activite » est un indice de 0 à 1 côté boîtier, de 0 à 100 sur le site. */
    private const ACTIVITY_SCALE = 100;

    /** Secondes passées à chaque allure sur l'intervalle, pour en déduire l'allure dominante. */
    private const GAIT_SECONDS = [
        'sec_couche' => Gait::Lying,
        'sec_immobile' => Gait::Rest,
        'sec_pas' => Gait::Walk,
        'sec_trot' => Gait::Trot,
        'sec_galop' => Gait::Canter,
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly DeviceRepository $devices,
        private readonly ReadingIngestor $ingestor,
    ) {
    }

    /**
     * La table n'existe que sur la base Supabase : en local (SQLite) et en test, l'import ne fait rien.
     */
    public function isAvailable(): bool
    {
        return $this->db->getDatabasePlatform() instanceof PostgreSQLPlatform
            && null !== $this->db->fetchOne("SELECT to_regclass('public.mesures')");
    }

    /**
     * @return array{readings: int, alerts: int} mesures importées et alertes déclenchées
     */
    public function import(int $limit = 500): array
    {
        if (!$this->isAvailable()) {
            return ['readings' => 0, 'alerts' => 0];
        }

        // Seulement les boîtiers associés à un cheval, et les lignes pas encore importées.
        $rows = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT m.*
            FROM public.mesures m
            JOIN device d ON d.serial = m.horse_id AND d.horse_id IS NOT NULL
            WHERE NOT EXISTS (SELECT 1 FROM reading r WHERE r.source_id = m.id)
            ORDER BY m.id
            LIMIT :limit
            SQL, ['limit' => $limit]);

        $bySerial = [];
        foreach ($rows as $row) {
            $bySerial[$row['horse_id']][] = $row;
        }

        $alerts = 0;
        foreach ($bySerial as $serial => $deviceRows) {
            $device = $this->devices->findOneBy(['serial' => (string) $serial]);
            if (!$device instanceof Device || null === $device->getHorse()) {
                continue;
            }

            $readings = array_map(
                fn (array $row): Reading => $this->ingestor->toReading($device->getHorse(), self::toInput($row))->setSourceId((int) $row['id']),
                $deviceRows,
            );
            $alerts += \count($this->ingestor->record($device, $readings));
        }

        return ['readings' => \count($rows), 'alerts' => $alerts];
    }

    /**
     * @param array<string, mixed> $row ligne de la table « mesures »
     */
    public static function toInput(array $row): ReadingInput
    {
        $receivedAt = new \DateTimeImmutable($row['received_at']);
        $measuredAt = null !== $row['measured_at'] ? new \DateTimeImmutable($row['measured_at']) : null;
        // Horloge du boîtier pas encore réglée (pas de fix GPS) : on se fie à l'heure de réception.
        $recordedAt = null !== $measuredAt && $measuredAt <= new \DateTimeImmutable('+5 minutes') ? $measuredAt : $receivedAt;

        $activity = self::inRange($row['activite'], 0, 1);
        $battery = self::inRange($row['batterie_pct'], 0, 100);

        return new ReadingInput(
            recordedAt: $recordedAt,
            latitude: self::inRange($row['lat'], -90, 90),
            longitude: self::inRange($row['lon'], -180, 180),
            temperature: self::inRange($row['t_int'], -40, 70),
            humidity: self::inRange($row['humidite'], 0, 100),
            activity: null !== $activity ? round($activity * self::ACTIVITY_SCALE, 1) : null,
            gait: self::gait($row),
            rolls: (int) (self::inRange($row['nb_roulades'], 0, 50) ?? 0),
            battery: null !== $battery ? (int) $battery : null,
        );
    }

    /**
     * L'allure envoyée par le boîtier si elle est connue, sinon celle où il a passé le plus de temps.
     *
     * @param array<string, mixed> $row
     */
    private static function gait(array $row): ?Gait
    {
        $label = mb_strtolower(trim((string) $row['comportement']));
        $gait = Gait::tryFrom(strtr($label, ['é' => 'e'])) ?? match ($label) {
            'immobile', 'arret', 'arrêt' => Gait::Rest,
            default => null,
        };
        if (null !== $gait) {
            return $gait;
        }

        $best = null;
        $bestSeconds = 0;
        foreach (self::GAIT_SECONDS as $column => $candidate) {
            if ((int) $row[$column] > $bestSeconds) {
                $best = $candidate;
                $bestSeconds = (int) $row[$column];
            }
        }

        return $best;
    }

    private static function inRange(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $value = (float) $value;

        return $value >= $min && $value <= $max ? $value : null;
    }
}
