<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o admin publica uma escala (PRD F4).
 * Ouvido pela infraestrutura de notificações (F7) para avisar a equipa.
 */
class SchedulePublished
{
    use Dispatchable;

    public function __construct(public Schedule $schedule) {}
}
