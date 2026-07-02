<?php

namespace App\Models;

use App\Enums\SwapStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\SwapRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwapRequest extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SwapRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'requester_employee_id',
        'target_employee_id',
        'requester_assignment_id',
        'target_assignment_id',
        'status',
        'validation',
        'admin_approval_required',
        'accepted_at',
        'decided_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SwapStatus::class,
            'validation' => 'array',
            'admin_approval_required' => 'boolean',
            'accepted_at' => 'datetime',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function requesterAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'requester_assignment_id');
    }

    public function targetAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'target_assignment_id');
    }
}
