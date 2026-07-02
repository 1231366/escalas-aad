<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ShiftTypeFactory;
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
}
