<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Absence extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AbsenceFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'schedule_id',
        'start_date',
        'end_date',
        'type',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'type' => AbsenceType::class,
            'coverage_gaps' => 'array',
            'reoptimized_at' => 'datetime',
            'reoptimization_conflicts' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Escala PUBLISHED afetada por esta ausência (calculada no registo por
     * AbsenceGapCalculator), se houver.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Data de corte da re-otimização parcial (issue #18): max(hoje+1, início
     * da ausência), sem ultrapassar o fim da escala nem recuar antes do seu
     * início. Null se a escala já não estiver PUBLISHED ou o corte cair
     * depois do fim do período.
     */
    public function reoptimizationCutoff(Schedule $schedule): ?CarbonInterface
    {
        if (! $schedule->isPublished()) {
            return null;
        }

        $tomorrow = Carbon::tomorrow();
        $cutoff = $this->start_date->greaterThan($tomorrow) ? $this->start_date->copy() : $tomorrow->copy();

        if ($cutoff->greaterThan($schedule->period_end)) {
            return null;
        }

        if ($cutoff->lessThan($schedule->period_start)) {
            $cutoff = $schedule->period_start->copy();
        }

        return $cutoff;
    }
}
