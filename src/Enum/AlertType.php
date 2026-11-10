<?php

namespace App\Enum;

enum AlertType: string
{
    case GeofenceExit = 'sortie-enclos';
    case TooHot = 'trop-chaud';
    case TooCold = 'trop-froid';
    case WetBlanket = 'couverture-mouillee';
    case RepeatedRolling = 'roulades';
    case ProlongedLying = 'couche-prolonge';
    case Agitation = 'agitation';
    case Inactivity = 'immobilite';
    case LowBattery = 'batterie-faible';

    public function label(): string
    {
        return match ($this) {
            self::GeofenceExit => 'Sortie d\'enclos',
            self::TooHot => 'Trop chaud sous la couverture',
            self::TooCold => 'Trop froid sous la couverture',
            self::WetBlanket => 'Couverture mouillée ou transpiration',
            self::RepeatedRolling => 'Roulades répétées',
            self::ProlongedLying => 'Couché depuis longtemps',
            self::Agitation => 'Agitation inhabituelle',
            self::Inactivity => 'Immobilité inhabituelle',
            self::LowBattery => 'Batterie faible',
        };
    }

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::GeofenceExit, self::RepeatedRolling => AlertSeverity::Critical,
            self::LowBattery => AlertSeverity::Info,
            default => AlertSeverity::Warning,
        };
    }
}
