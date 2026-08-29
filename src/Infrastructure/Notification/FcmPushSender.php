<?php

namespace QOR\App\Infrastructure\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use QOR\App\Domain\Notification\NotificationSender;
use Throwable;

class FcmPushSender implements NotificationSender
{
    public function send(int $userId, array $payload): void
    {
        $deviceToken = $payload['device_token'] ?? null;
        $title = $payload['title'] ?? null;
        $body = $payload['body'] ?? null;

        if (! is_string($deviceToken) || $deviceToken === '') {
            throw new InvalidArgumentException(
                'Payload de notificação push inválido: token do dispositivo ausente.'
            );
        }

        if (! is_string($title) || $title === '' || ! is_string($body) || $body === '') {
            throw new InvalidArgumentException(
                'Payload de notificação push inválido: título ou corpo da mensagem ausente.'
            );
        }

        /** @var string $serverKey */
        $serverKey = config('qor.notifications.fcm.server_key');
        /** @var string $endpoint */
        $endpoint = config('qor.notifications.fcm.endpoint');

        // Provider-level failures are caught and logged here, never surfaced to the
        // caller (notifications/design.md's Error Handling Strategy) — a transient
        // FCM outage must not fail the fan-facing request that triggered the send.
        try {
            $response = Http::withHeaders([
                'Authorization' => "key={$serverKey}",
            ])->post($endpoint, [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ]);

            if ($response->failed()) {
                Log::error("Falha ao enviar notificação push via FCM para o usuário {$userId}.", [
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Falha ao enviar notificação push via FCM para o usuário {$userId}.", [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
