<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ShiftTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftType extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ShiftTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'starts_at',
        'ends_at',
        'hours',
        'color',
    ];

    protected function casts(): array
    {
        return ['hours' => 'float'];
    }

    public function coverageRules(): HasMany
    {
        return $this->hasMany(CoverageRule::class);
    }

    /**
     * Ordem lógica dos turnos (M, T, N) em vez de alfabética.
     */
    public function scopeOrderedByShift(Builder $query): Builder
    {
        return $query->orderByRaw("CASE code WHEN 'M' THEN 1 WHEN 'T' THEN 2 WHEN 'N' THEN 3 ELSE 4 END");
    }
}
