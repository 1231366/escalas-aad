<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Avisa a colega-alvo (e, em database, os admins) de um novo pedido de troca
 * (PRD F5/F7). Só a colega recebe email — os admins são apenas informados
 * in-app aqui; ganham email mais tarde se a troca precisar da aprovação
 * deles (SwapAwaitingApproval).
 */
class SwapRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swapRequest) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! $notifiable->isAdmin() && $notifiable->wantsEmailFor('swap_request')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido de troca de turno')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver pedido', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_requested',
            'message' => $this->message(),
            'swap_request_id' => $this->swapRequest->id,
        ];
    }

    private function message(): string
    {
        $requester = $this->swapRequest->requester;
        $date = $this->swapRequest->requesterAssignment->date->format('d/m/Y');

        return "{$requester->name} quer trocar contigo o turno de {$date}.";
    }

    private function url(): string
    {
        if (Route::has('swaps.index')) {
            return route('swaps.index');
        }

        return route('dashboard');
    }
}
