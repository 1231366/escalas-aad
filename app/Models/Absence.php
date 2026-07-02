<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AbsenceFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'employee_id',
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
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
