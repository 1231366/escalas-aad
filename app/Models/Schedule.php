<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'period_start',
        'period_end',
        'status',
        'generated_at',
        'generated_by',
        'published_at',
        'solver_stats',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => ScheduleStatus::class,
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
            'solver_stats' => 'array',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === ScheduleStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === ScheduleStatus::Draft;
    }
}
