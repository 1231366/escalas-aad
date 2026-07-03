<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * A colega aceitou a troca e a organização exige aprovação do admin (PRD F5,
 * Organization::swapRequiresAdminApproval()). Só é enviada a admins.
 */
class SwapAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swapRequest) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Troca à espera de aprovação')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Rever troca', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_awaiting_approval',
            'message' => $this->message(),
            'swap_request_id' => $this->swapRequest->id,
        ];
    }

    private function message(): string
    {
        $requester = $this->swapRequest->requester;
        $target = $this->swapRequest->target;
        $date = $this->swapRequest->requesterAssignment->date->format('d/m/Y');

        return "{$requester->name} e {$target->name} aceitaram trocar o turno de {$date}. Precisa da tua aprovação.";
    }

    private function url(): string
    {
        if (Route::has('admin.swaps.index')) {
            return route('admin.swaps.index');
        }

        return route('dashboard');
    }
}
