<?php

namespace Tests\Feature\Infrastructure\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use QOR\App\Infrastructure\Auth\LaravelEmailVerificationAdapter;
use QOR\App\Infrastructure\Notifications\PendingEmailVerificationNotification;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class LaravelEmailVerificationAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_an_already_verified_account_WHEN_verifying_THEN_it_returns_true_without_checking_the_hash(): void
    {
        $user = UserModel::factory()->create();
        $adapter = new LaravelEmailVerificationAdapter();

        $result = $adapter->verify((int) $user->id, 'hash-invalido-qualquer');

        $this->assertTrue($result);
    }

    public function test_GIVEN_an_unverified_account_WHEN_verifying_with_the_wrong_hash_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->unverified()->create();
        $adapter = new LaravelEmailVerificationAdapter();

        $result = $adapter->verify((int) $user->id, 'hash-invalido');

        $this->assertFalse($result);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_GIVEN_no_pending_email_WHEN_sending_the_pending_email_notification_THEN_nothing_is_sent(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['pending_email' => null]);
        $adapter = new LaravelEmailVerificationAdapter();

        $adapter->sendForPendingEmail((int) $user->id);

        Notification::assertNothingSent();
    }

    public function test_GIVEN_a_pending_email_WHEN_sending_the_pending_email_notification_THEN_it_notifies_the_new_address(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['pending_email' => 'novo@example.com']);
        $adapter = new LaravelEmailVerificationAdapter();

        $adapter->sendForPendingEmail((int) $user->id);

        Notification::assertSentOnDemand(PendingEmailVerificationNotification::class);
    }

    public function test_GIVEN_no_pending_email_WHEN_confirming_the_pending_email_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create(['pending_email' => null]);
        $adapter = new LaravelEmailVerificationAdapter();

        $result = $adapter->confirmPendingEmail((int) $user->id, sha1('irrelevante'));

        $this->assertFalse($result);
    }

    public function test_GIVEN_a_pending_email_WHEN_confirming_with_the_wrong_hash_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create(['pending_email' => 'novo@example.com']);
        $adapter = new LaravelEmailVerificationAdapter();

        $result = $adapter->confirmPendingEmail((int) $user->id, 'hash-invalido');

        $this->assertFalse($result);
        $this->assertSame('novo@example.com', $user->fresh()->pending_email);
    }

    public function test_GIVEN_a_pending_email_WHEN_confirming_with_the_correct_hash_THEN_it_becomes_the_active_email(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'antigo@example.com',
            'pending_email' => 'novo@example.com',
        ]);
        $adapter = new LaravelEmailVerificationAdapter();

        $result = $adapter->confirmPendingEmail((int) $user->id, sha1('novo@example.com'));

        $this->assertTrue($result);
        $fresh = $user->fresh();
        $this->assertSame('novo@example.com', $fresh->email);
        $this->assertNull($fresh->pending_email);
        $this->assertNotNull($fresh->email_verified_at);
    }
}
