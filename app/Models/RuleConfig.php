<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class RuleConfig extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * Valor de uma regra para a org autenticada, com default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $config = static::query()->where('key', $key)->first();

        return $config ? $config->value : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Defaults do ADR-0003/0004 — usados no seed e como fallback. */
    public static function defaults(): array
    {
        return [
            'hour_bank_weekly_tolerance' => 4.0,
            'max_consecutive_work_days' => 6,
            'ff_window_weeks' => 7,
            'ff_monthly' => true,
            'forbidden_transitions' => [['N', 'M'], ['N', 'T'], ['T', 'M']],
        ];
    }
}
