<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewStaffAccountCredentials extends Notification
{
    public function __construct(public readonly string $temporaryPassword) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua conta de equipe no Qual o Rock?')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Uma conta de equipe foi criada para você no painel administrativo do Qual o Rock?.')
            ->line("Senha temporária: {$this->temporaryPassword}")
            ->line('Você precisará alterá-la no primeiro acesso.');
    }
}
