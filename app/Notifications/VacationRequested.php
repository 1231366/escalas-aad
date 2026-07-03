<?php

namespace App\Notifications;

use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa os admins da organização de um novo pedido de férias (PRD F6/F7). O
 * impacto na cobertura já vem calculado (VacationRequest::impact, ver
 * SolverClient::vacationImpact) e é mostrado no ecrã de decisão do admin —
 * esta notificação é só o aviso de que há um pedido novo.
 */
class VacationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VacationRequest $vacationRequest) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor('vacation_requested')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo pedido de férias')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver pedido', route('admin.vacations.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vacation_requested',
            'message' => $this->message(),
            'vacation_request_id' => $this->vacationRequest->id,
            'employee_id' => $this->vacationRequest->employee_id,
        ];
    }

    private function message(): string
    {
        $employee = $this->vacationRequest->employee;

        return "{$employee->name} pediu férias de {$this->vacationRequest->start_date->format('d/m/Y')} a {$this->vacationRequest->end_date->format('d/m/Y')}.";
    }
}
