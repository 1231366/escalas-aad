<?php

namespace App\Enums;

/**
 * Restrição de horário da funcionária (ver CONTEXT.md).
 * DIA: só turnos M/T · NOITE: só turnos N · HIBRIDO: qualquer.
 */
enum Regime: string
{
    case Dia = 'DIA';
    case Noite = 'NOITE';
    case Hibrido = 'HIBRIDO';

    public function elegivelNoite(): bool
    {
        return $this !== self::Dia;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dia => 'Só dia (M/T)',
            self::Noite => 'Só noite (N)',
            self::Hibrido => 'Híbrido',
        };
    }
}
