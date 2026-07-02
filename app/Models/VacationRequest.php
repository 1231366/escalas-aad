<?php

namespace App\Models;

use App\Enums\VacationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\VacationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<VacationRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'start_date',
        'end_date',
        'status',
        'impact',
        'note',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => VacationStatus::class,
            'impact' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
