<?php

namespace App\Enum;

/**
 * Allure dominante sur l'intervalle d'une mesure, classée par le boîtier (F3).
 */
enum Gait: string
{
    case Lying = 'couche';
    case Rest = 'repos';
    case Walk = 'pas';
    case Trot = 'trot';
    case Canter = 'galop';

    public function label(): string
    {
        return match ($this) {
            self::Lying => 'Couché',
            self::Rest => 'Repos',
            self::Walk => 'Pas',
            self::Trot => 'Trot',
            self::Canter => 'Galop',
        };
    }

    public function isMoving(): bool
    {
        return \in_array($this, [self::Walk, self::Trot, self::Canter], true);
    }
}
