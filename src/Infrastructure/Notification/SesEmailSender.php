<?php

namespace QOR\App\Infrastructure\Notification;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use QOR\App\Domain\Notification\NotificationSender;

/**
 * Sends via Laravel's Mail facade rather than a direct SesClient — the AWS SDK
 * isn't installed as a first-class dependency (only pulled transitively by the
 * S3 flysystem adapter), so this reuses the framework's `ses` mail driver
 * (config/mail.php), consistent with S3's existing AWS credentials pattern.
 */
class SesEmailSender implements NotificationSender
{
    public function send(int $userId, array $payload): void
    {
        $email = $payload['email'] ?? null;
        $subject = $payload['subject'] ?? null;
        $body = $payload['body'] ?? null;

        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Payload de e-mail inválido: endereço de e-mail ausente ou inválido.'
            );
        }

        if (! is_string($subject) || $subject === '' || ! is_string($body) || $body === '') {
            throw new InvalidArgumentException(
                'Payload de e-mail inválido: assunto ou corpo da mensagem ausente.'
            );
        }

        Mail::to($email)->send(new NotificationMailable($subject, $body));
    }
}
