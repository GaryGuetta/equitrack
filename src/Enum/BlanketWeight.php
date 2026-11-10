<?php

namespace App\Enum;

/**
 * Grammage de couverture, du plus léger au plus chaud.
 * L'ordre des cas sert d'échelle : rank() permet de monter ou descendre d'un cran.
 */
enum BlanketWeight: string
{
    case None = 'aucune';
    case RainSheet = 'impermeable';
    case Light = 'legere';
    case Medium = 'moyenne';
    case Heavy = 'chaude';
    case ExtraHeavy = 'tres-chaude';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Pas de couverture',
            self::RainSheet => 'Imperméable sans garnissage (0 g)',
            self::Light => 'Couverture légère (100–150 g)',
            self::Medium => 'Couverture moyenne (200–250 g)',
            self::Heavy => 'Couverture chaude (300–350 g)',
            self::ExtraHeavy => 'Couverture très chaude (400 g et plus)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::None => 'Aucune',
            self::RainSheet => '0 g',
            self::Light => '100–150 g',
            self::Medium => '200–250 g',
            self::Heavy => '300–350 g',
            self::ExtraHeavy => '400 g+',
        };
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public static function fromRank(int $rank): self
    {
        $cases = self::cases();

        return $cases[max(0, min(\count($cases) - 1, $rank))];
    }
}
