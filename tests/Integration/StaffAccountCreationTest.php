<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\NewStaffAccountCredentials;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('given a super admin when creating a staff account then the row has role admin and must_change_password true', function () {
    Notification::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'nova-moderadora@example.com',
    ])->assertCreated();

    $staff = User::query()->where('email', 'nova-moderadora@example.com')->first();
    expect($staff)->not->toBeNull()
        ->and($staff->role)->toBe(Role::Admin)
        ->and($staff->must_change_password)->toBeTrue();
});

it('given a staff account is created then an email is delivered to that address containing a password, and the API response does not', function () {
    Notification::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $response = $this->postJson('/api/admin/staff', [
        'name' => 'Nova Moderadora',
        'email' => 'nova-moderadora-2@example.com',
    ])->assertCreated();

    expect($response->json())->toBe([
        'id' => $response->json('id'),
        'name' => 'Nova Moderadora',
        'email' => 'nova-moderadora-2@example.com',
        'role' => 'admin',
    ]);

    $staff = User::query()->where('email', 'nova-moderadora-2@example.com')->first();

    Notification::assertSentTo($staff, NewStaffAccountCredentials::class, function (NewStaffAccountCredentials $notification) use ($staff) {
        $mail = $notification->toMail($staff);
        $body = implode(' ', $mail->introLines);

        return strlen($notification->temporaryPassword) >= 8
            && str_contains($body, $notification->temporaryPassword);
    });
});
