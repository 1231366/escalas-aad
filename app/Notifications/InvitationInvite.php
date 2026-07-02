<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = $this->invitation->organization->name;

        return (new MailMessage)
            ->subject("Convite para a equipa de {$org}")
            ->greeting("Olá {$this->invitation->name}!")
            ->line("Foste convidada para te juntares à equipa de {$org} na app de escalas.")
            ->line("O teu perfil já vem preparado: {$this->invitation->regime->label()}, {$this->invitation->contract->label()}.")
            ->action('Criar a minha conta', $this->invitation->acceptUrl())
            ->line('O convite expira em '.$this->invitation->expires_at->diffForHumans().'.')
            ->salutation('Até já!');
    }
}
