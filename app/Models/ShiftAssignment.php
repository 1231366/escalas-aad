<?php

namespace App\Models;

use App\Enums\AssignmentOrigin;
use Database\Factories\ShiftAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma célula da grelha da escala. shift_type_id = null significa folga (F).
 * Sem scope de org próprio: o acesso é sempre via Schedule (que tem scope).
 */
class ShiftAssignment extends Model
{
    /** @use HasFactory<ShiftAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'employee_id',
        'date',
        'shift_type_id',
        'origin',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'origin' => AssignmentOrigin::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function isDayOff(): bool
    {
        return $this->shift_type_id === null;
    }
}
