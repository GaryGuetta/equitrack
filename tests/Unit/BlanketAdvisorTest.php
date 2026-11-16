<?php

namespace App\Tests\Unit;

use App\Advice\BlanketAdvisor;
use App\Entity\Horse;
use App\Entity\Reading;
use App\Enum\BlanketWeight;
use App\Enum\Clip;
use App\Weather\Forecast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BlanketAdvisorTest extends TestCase
{
    public static function gridProvider(): iterable
    {
        yield 'non tondu, doux' => [Clip::None, 12.0, BlanketWeight::None];
        yield 'non tondu, gel' => [Clip::None, -2.0, BlanketWeight::Medium];
        yield 'tonte partielle, frais' => [Clip::Partial, 7.0, BlanketWeight::Light];
        yield 'tonte complète, froid' => [Clip::Full, 2.0, BlanketWeight::Heavy];
        yield 'tonte complète, grand froid' => [Clip::Full, -10.0, BlanketWeight::ExtraHeavy];
        yield 'chaud' => [Clip::Full, 18.0, BlanketWeight::None];
    }

    #[DataProvider('gridProvider')]
    public function testGrid(Clip $clip, float $temperature, BlanketWeight $expected): void
    {
        $advice = new BlanketAdvisor()->advise($this->horse($clip), $this->forecast($temperature), null);

        self::assertSame($expected, $advice->recommended);
    }

    public function testColdSensitiveHorseGetsOneMoreStep(): void
    {
        $horse = $this->horse(Clip::Partial)->setColdSensitive(true);

        $advice = new BlanketAdvisor()->advise($horse, $this->forecast(7.0), null);

        self::assertSame(BlanketWeight::Medium, $advice->recommended);
    }

    public function testRainWithoutShelterCallsForRainSheet(): void
    {
        $advice = new BlanketAdvisor()->advise($this->horse(Clip::None), $this->forecast(12.0, rain: 80), null);

        self::assertSame(BlanketWeight::RainSheet, $advice->recommended);
    }

    public function testSweatingUnderBlanketRecommendsLighter(): void
    {
        $horse = $this->horse(Clip::None)->setCurrentBlanket(BlanketWeight::Heavy);
        $reading = (new Reading($horse, new \DateTimeImmutable('-5 minutes')))->setTemperature(34.0)->setHumidity(88.0);

        // La grille seule donnerait « moyenne » à -2 °C ; le microclimat mesuré impose de descendre sous la « chaude ».
        $advice = new BlanketAdvisor()->advise($horse, $this->forecast(-2.0), $reading);

        self::assertSame(BlanketWeight::Medium, $advice->recommended);
        self::assertSame('lighter', $advice->verdict());
    }

    public function testNoAdviceWithoutForecastEvenWithReading(): void
    {
        $horse = $this->horse(Clip::Full);
        $reading = (new Reading($horse, new \DateTimeImmutable('-5 minutes')))->setTemperature(27.0);

        self::assertNull(new BlanketAdvisor()->advise($horse, null, $reading));
    }

    public function testNoAdviceWithoutAnyTemperature(): void
    {
        self::assertNull(new BlanketAdvisor()->advise($this->horse(Clip::None), null, null));
    }

    private function horse(Clip $clip): Horse
    {
        return (new Horse())->setName('Test')->setClip($clip);
    }

    private function forecast(float $min, int $rain = 0): Forecast
    {
        return new Forecast($min + 3, $min, $rain, 10.0);
    }
}
