<?php

namespace App\Enums;

enum AbsenceType: string
{
    case Sick = 'SICK';
    case Unjustified = 'UNJUSTIFIED';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Baixa médica',
            self::Unjustified => 'Falta',
            self::Other => 'Outro',
        };
    }
}
