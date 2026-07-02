<?php

namespace App\Enums;

/**
 * Ciclo de vida de um pedido de troca (PRD F5):
 * PENDING → alvo aceita (ACCEPTED) ou recusa (DECLINED)
 * ACCEPTED → se a org exigir aprovação, admin aprova (→APPLIED) ou rejeita (REJECTED);
 *            senão aplica logo (APPLIED). Requerente pode cancelar enquanto PENDING.
 */
enum SwapStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Rejected = 'REJECTED';
    case Applied = 'APPLIED';
    case Cancelled = 'CANCELLED';
}
