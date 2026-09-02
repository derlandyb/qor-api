<?php

namespace QOR\App\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly string $purpose,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /** Exposed for tests — the code itself is never logged/returned in any API response. */
    public function getCode(): string
    {
        return $this->code;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        /** @var int $ttlMinutes */
        $ttlMinutes = config('qor.auth.otp_ttl_minutes');

        $subject = $this->purpose === 'password_reset'
            ? 'Seu código para redefinir a senha'
            : 'Seu código de verificação';

        return (new MailMessage())
            ->subject($subject)
            ->line("Seu código é: {$this->code}")
            ->line("Este código expira em {$ttlMinutes} minutos.")
            ->line('Se você não solicitou este código, ignore esta mensagem.');
    }
}
