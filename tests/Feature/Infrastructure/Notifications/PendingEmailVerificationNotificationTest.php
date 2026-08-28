<?php

namespace Tests\Feature\Infrastructure\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Notifications\PendingEmailVerificationNotification;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class PendingEmailVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_pending_email_WHEN_building_the_mail_THEN_it_links_to_the_signed_confirmation_url(): void
    {
        $user = UserModel::factory()->create(['pending_email' => 'novo@example.com']);
        $notification = new PendingEmailVerificationNotification('novo@example.com');

        $mail = $notification->toMail($user);

        $this->assertSame(['mail'], $notification->via($user));
        $this->assertSame('Confirme seu novo e-mail', $mail->subject);
        $this->assertNotNull($mail->actionUrl);
        $this->assertStringContainsString('/email/pending/verify/', $mail->actionUrl);
        $this->assertStringContainsString((string) $user->id, $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
    }
}
