<?php

namespace Tests\Feature\Infrastructure\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use QOR\App\Infrastructure\Notification\FcmPushSender;
use Tests\TestCase;

class FcmPushSenderTest extends TestCase
{
    public function test_GIVEN_a_well_formed_payload_WHEN_sending_THEN_it_dispatches_to_fcm_without_error(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['success' => 1], 200),
        ]);

        $sender = new FcmPushSender();

        $sender->send(1, [
            'device_token' => 'device-token-abc',
            'title' => 'Seu evento favorito está chegando',
            'body' => 'O show começa em breve perto de você.',
        ]);

        Http::assertSent(function ($request) {
            return $request['to'] === 'device-token-abc'
                && $request['notification']['title'] === 'Seu evento favorito está chegando';
        });
    }

    public function test_GIVEN_a_payload_missing_the_device_token_WHEN_sending_THEN_it_throws_and_never_calls_fcm(): void
    {
        Http::fake();

        $sender = new FcmPushSender();

        $this->expectException(InvalidArgumentException::class);

        try {
            $sender->send(1, ['title' => 'Título', 'body' => 'Corpo']);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_GIVEN_a_payload_missing_the_title_and_body_WHEN_sending_THEN_it_throws(): void
    {
        Http::fake();

        $sender = new FcmPushSender();

        $this->expectException(InvalidArgumentException::class);

        $sender->send(1, ['device_token' => 'device-token-abc']);
    }

    public function test_GIVEN_fcm_responds_with_a_failure_WHEN_sending_THEN_it_does_not_throw_and_logs_the_failure(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['error' => 'InvalidRegistration'], 400),
        ]);
        Log::spy();

        $sender = new FcmPushSender();

        $sender->send(1, [
            'device_token' => 'device-token-abc',
            'title' => 'Título',
            'body' => 'Corpo',
        ]);

        Log::shouldHaveReceived('error')->once();
    }
}
