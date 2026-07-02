<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\CoverageRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageRule extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<CoverageRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'shift_type_id',
        'weekday',
        'required',
    ];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'required' => 'integer'];
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }
}
