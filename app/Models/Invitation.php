<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Enums\Role;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'email',
        'name',
        'role',
        'regime',
        'contract',
        'fixa_noite',
        'token',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'regime' => Regime::class,
            'contract' => ContractType::class,
            'fixa_noite' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function acceptUrl(): string
    {
        return route('invitations.show', $this->token);
    }

    /**
     * Link wa.me com a mensagem de convite pronta a enviar (PRD F2).
     */
    public function whatsappUrl(): string
    {
        $message = __('Olá :name! 👋 Foste convidada para te juntares à equipa de :org na app de escalas. Cria a tua conta aqui: :url', [
            'name' => $this->name,
            'org' => $this->organization->name,
            'url' => $this->acceptUrl(),
        ]);

        return 'https://wa.me/?text='.rawurlencode($message);
    }
}
