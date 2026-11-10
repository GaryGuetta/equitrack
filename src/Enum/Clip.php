<?php

namespace App\Enum;

/**
 * Tonte du cheval : elle change fortement le besoin de couverture.
 */
enum Clip: string
{
    case None = 'aucune';
    case Partial = 'partielle';
    case Full = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Non tondu',
            self::Partial => 'Tonte partielle',
            self::Full => 'Tonte complète',
        };
    }
}
