<?php

namespace QOR\App\Infrastructure\Notification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use QOR\App\Domain\Notification\NotificationSender;
use Throwable;

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

        // A malformed/incomplete payload must never fail the fan-facing request that
        // triggered the dispatch, same as a provider outage.
        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($subject) || $subject === '' || ! is_string($body) || $body === '') {
            Log::error("Payload de e-mail inválido para o usuário {$userId}: endereço, assunto ou corpo da mensagem ausente ou inválido.");

            return;
        }

        // Provider-level failures are caught and logged here, never surfaced to the
        // caller (notifications/design.md's Error Handling Strategy) — a transient
        // SES outage must not fail the fan-facing request that triggered the send.
        try {
            Mail::to($email)->send(new NotificationMailable($subject, $body));
        } catch (Throwable $e) {
            Log::error("Falha ao enviar e-mail via SES para o usuário {$userId}.", [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
