<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Avisa a equipa (PRD F7) quando uma escala é publicada (App\Events\SchedulePublished).
 */
class SchedulePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Nomes dos meses por extenso em português (evita depender do locale global da app/Carbon).
     */
    private const MESES = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'março',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    public function __construct(public Schedule $schedule) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor('schedule_published')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Escala publicada')
            ->greeting("Olá {$notifiable->name}!")
            ->line($this->message())
            ->action('Ver escala', $this->url($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'schedule_published',
            'message' => $this->message(),
            'schedule_id' => $this->schedule->id,
            'period_start' => $this->schedule->period_start->toDateString(),
        ];
    }

    private function message(): string
    {
        return "A escala de {$this->monthLabel()} já está publicada.";
    }

    private function monthLabel(): string
    {
        $date = $this->schedule->period_start;

        return self::MESES[$date->month].' de '.$date->year;
    }

    /**
     * Aponta para a escala da funcionária ou para a lista de escalas do admin.
     * Usa Route::has porque estas rotas nascem noutra slice em paralelo (F4).
     */
    private function url(object $notifiable): string
    {
        if ($notifiable->isAdmin() && Route::has('admin.schedules.index')) {
            return route('admin.schedules.index');
        }

        if (Route::has('my-schedule')) {
            return route('my-schedule');
        }

        return route('dashboard');
    }
}
