<?php

namespace QOR\App\Infrastructure\Notification;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use QOR\App\Domain\Notification\NotificationSender;
use RuntimeException;

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
            throw new RuntimeException("Falha ao enviar notificação push via FCM para o usuário {$userId}.");
        }
    }
}
