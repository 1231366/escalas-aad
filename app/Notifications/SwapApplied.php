<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * A troca foi aplicada à escala (PRD F5 passo 4): ambas as funcionárias
 * ganham email+in-app; os admins só in-app (já foram avisados antes, se
 * aplicável, por SwapRequested/SwapAwaitingApproval).
 */
class SwapApplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swapRequest) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! $notifiable->isAdmin() && $notifiable->wantsEmailFor('swap_decided')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Troca de turno aplicada')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver a minha escala', $this->url($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_applied',
            'message' => $this->message(),
            'swap_request_id' => $this->swapRequest->id,
        ];
    }

    private function message(): string
    {
        $requester = $this->swapRequest->requester;
        $target = $this->swapRequest->target;
        $date = $this->swapRequest->requesterAssignment->date->format('d/m/Y');

        return "A troca entre {$requester->name} e {$target->name} para {$date} foi aplicada à escala.";
    }

    private function url(object $notifiable): string
    {
        if ($notifiable->isAdmin() && Route::has('admin.swaps.index')) {
            return route('admin.swaps.index');
        }

        if (Route::has('my-schedule')) {
            return route('my-schedule');
        }

        return route('dashboard');
    }
}
