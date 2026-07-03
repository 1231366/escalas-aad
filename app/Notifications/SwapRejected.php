<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * A troca foi rejeitada — ou porque a revalidação do solver falhou ao
 * aceitar (o estado da escala mudou entretanto), ou porque o admin a
 * rejeitou depois de ACCEPTED. Explica o motivo às duas funcionárias.
 */
class SwapRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swapRequest, public string $reason) {}

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
            ->subject('Troca de turno rejeitada')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver os meus pedidos', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_rejected',
            'message' => $this->message(),
            'swap_request_id' => $this->swapRequest->id,
            'reason' => $this->reason,
        ];
    }

    private function message(): string
    {
        $requester = $this->swapRequest->requester;
        $target = $this->swapRequest->target;
        $date = $this->swapRequest->requesterAssignment->date->format('d/m/Y');

        return "A troca entre {$requester->name} e {$target->name} para {$date} foi rejeitada: {$this->reason}";
    }

    private function url(): string
    {
        if (Route::has('swaps.index')) {
            return route('swaps.index');
        }

        return route('dashboard');
    }
}
