<?php

namespace QOR\App\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class PendingEmailVerificationNotification extends Notification
{
    public function __construct(
        private readonly string $newEmail,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(UserModel $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'email.pending.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->id, 'hash' => sha1($this->newEmail)],
        );

        return (new MailMessage())
            ->subject('Confirme seu novo e-mail')
            ->line('Clique no link abaixo para confirmar seu novo endereço de e-mail.')
            ->action('Confirmar e-mail', $url)
            ->line('Se você não solicitou essa alteração, ignore esta mensagem.');
    }
}
