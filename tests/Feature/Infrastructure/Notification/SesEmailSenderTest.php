<?php

namespace Tests\Feature\Infrastructure\Notification;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use QOR\App\Infrastructure\Notification\SesEmailSender;
use Tests\TestCase;

class SesEmailSenderTest extends TestCase
{
    public function test_GIVEN_a_well_formed_payload_WHEN_sending_THEN_an_email_is_dispatched_without_error(): void
    {
        Mail::fake();

        $sender = new SesEmailSender();

        $sender->send(1, [
            'email' => 'fan@example.com',
            'subject' => 'Seu evento favorito está chegando',
            'body' => 'O show começa em breve perto de você.',
        ]);

        Mail::assertSent(function (Mailable $mailable) {
            return $mailable->hasTo('fan@example.com');
        });
    }

    public function test_GIVEN_a_payload_with_an_invalid_email_WHEN_sending_THEN_it_logs_and_never_sends(): void
    {
        Mail::fake();
        Log::spy();

        $sender = new SesEmailSender();

        $sender->send(1, ['email' => 'not-an-email', 'subject' => 'Título', 'body' => 'Corpo']);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    public function test_GIVEN_a_payload_missing_the_subject_and_body_WHEN_sending_THEN_it_logs_and_never_sends(): void
    {
        Mail::fake();
        Log::spy();

        $sender = new SesEmailSender();

        $sender->send(1, ['email' => 'fan@example.com']);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }
}
