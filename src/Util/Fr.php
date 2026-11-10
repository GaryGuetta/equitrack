<?php

namespace App\Util;

/**
 * Nombres écrits à la française dans les messages (virgule décimale).
 */
final class Fr
{
    public static function number(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
