<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * A requerente cancelou o pedido antes de a colega decidir — avisa a colega-alvo (PRD F5).
 */
class SwapCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swapRequest) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor('swap_decided')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido de troca cancelado')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver os meus pedidos', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_cancelled',
            'message' => $this->message(),
            'swap_request_id' => $this->swapRequest->id,
        ];
    }

    private function message(): string
    {
        $requester = $this->swapRequest->requester;
        $date = $this->swapRequest->requesterAssignment->date->format('d/m/Y');

        return "{$requester->name} cancelou o pedido de troca do turno de {$date}.";
    }

    private function url(): string
    {
        if (Route::has('swaps.index')) {
            return route('swaps.index');
        }

        return route('dashboard');
    }
}
