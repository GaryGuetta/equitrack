<?php

namespace App\Tests\Unit;

use App\Enum\Gait;
use App\Monitoring\MeasureImporter;
use PHPUnit\Framework\TestCase;

final class MeasureImporterTest extends TestCase
{
    /** La ligne de test réellement écrite dans Supabase par le boîtier cheval-01. */
    private const ROW = [
        'id' => 1, 'horse_id' => 'cheval-01', 'measured_at' => null, 'received_at' => '2026-11-16 10:55:31.729769+00',
        'lat' => 43.6, 'lon' => 1.44, 'satellites' => null, 'activite' => 0.02, 't_int' => 31.2, 'humidite' => null,
        'batterie_mv' => null, 'batterie_pct' => null, 'signal' => null, 'reseau' => 'LTE-M', 'comportement' => null,
        'sec_immobile' => null, 'sec_pas' => null, 'sec_trot' => null, 'sec_galop' => null, 'sec_couche' => null,
        'nb_roulades' => null, 'vitesse' => null, 'duree_fix_gps' => null, 'precision_gps' => null, 'altitude' => null,
    ];

    public function testMapsSupabaseColumns(): void
    {
        $input = MeasureImporter::toInput(self::ROW);

        self::assertSame('2026-11-16T10:55:31+00:00', $input->recordedAt->format(\DATE_ATOM));
        self::assertSame(43.6, $input->latitude);
        self::assertSame(1.44, $input->longitude);
        self::assertSame(31.2, $input->temperature);
        self::assertSame(2.0, $input->activity);
        self::assertSame(0, $input->rolls);
        self::assertNull($input->gait);
        self::assertNull($input->battery);
    }

    public function testPrefersDeviceTimeUnlessInTheFuture(): void
    {
        self::assertSame('10:50', MeasureImporter::toInput(['measured_at' => '2026-11-16 10:50:00+00'] + self::ROW)->recordedAt->format('H:i'));
        self::assertSame('10:55', MeasureImporter::toInput(['measured_at' => '2099-01-01 00:00:00+00'] + self::ROW)->recordedAt->format('H:i'));
    }

    public function testGaitFromLabelOrDominantSeconds(): void
    {
        self::assertSame(Gait::Trot, MeasureImporter::toInput(['comportement' => 'Trot'] + self::ROW)->gait);
        self::assertSame(Gait::Lying, MeasureImporter::toInput(['comportement' => 'couché'] + self::ROW)->gait);
        self::assertSame(Gait::Walk, MeasureImporter::toInput(['sec_pas' => 70, 'sec_immobile' => 40, 'sec_trot' => 10] + self::ROW)->gait);
    }

    public function testOutOfRangeValuesAreDropped(): void
    {
        $input = MeasureImporter::toInput(['t_int' => 120, 'batterie_pct' => 140, 'lat' => 0, 'nb_roulades' => 3] + self::ROW);

        self::assertNull($input->temperature);
        self::assertNull($input->battery);
        self::assertSame(0.0, $input->latitude);
        self::assertSame(3, $input->rolls);
    }
}
