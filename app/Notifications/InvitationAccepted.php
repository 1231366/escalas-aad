<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invitation $invitation,
        public User $newUser,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor('invitation_accepted')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite aceite')
            ->greeting("Olá {$notifiable->name}!")
            ->line("{$this->newUser->name} ({$this->newUser->email}) aceitou o convite e já faz parte da equipa.")
            ->action('Ver equipa', route('admin.invitations.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invitation_accepted',
            'message' => "{$this->newUser->name} aceitou o convite e juntou-se à equipa.",
            'user_id' => $this->newUser->id,
        ];
    }
}
