<?php

namespace App\Enums;

enum ContractType: string
{
    case H37_30 = 'H37_30';
    case H40 = 'H40';

    public function weeklyHours(): float
    {
        return match ($this) {
            self::H37_30 => 37.5,
            self::H40 => 40.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::H37_30 => '37h30/semana',
            self::H40 => '40h/semana',
        };
    }
}
