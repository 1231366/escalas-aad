<?php

namespace App\Notifications;

use App\Enums\VacationStatus;
use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Avisa a funcionária da decisão do admin sobre o seu pedido de férias
 * (PRD F6/F7).
 */
class VacationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VacationRequest $vacationRequest) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor('vacation_decided')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido de férias '.$this->statusLabel())
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver pedidos', $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vacation_decided',
            'message' => $this->message(),
            'vacation_request_id' => $this->vacationRequest->id,
            'status' => $this->vacationRequest->status->value,
        ];
    }

    private function message(): string
    {
        return "O teu pedido de férias de {$this->vacationRequest->start_date->format('d/m/Y')} a {$this->vacationRequest->end_date->format('d/m/Y')} foi {$this->statusLabel()}.";
    }

    private function statusLabel(): string
    {
        return $this->vacationRequest->status === VacationStatus::Approved ? 'aprovado' : 'recusado';
    }

    private function url(): string
    {
        return Route::has('vacations.index') ? route('vacations.index') : route('dashboard');
    }
}
