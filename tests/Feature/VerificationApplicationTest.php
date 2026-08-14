<?php

use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\VerificationApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function validVerificationPayload(array $overrides = []): array
{
    return array_merge([
        'business_name' => 'Rock in Rio Produções',
        'account_type' => 'promoter',
        'contact_email' => 'contato@rockinrio.example',
        'contact_phone' => '27999998888',
        'social_link' => 'https://instagram.com/rockinrioproducoes',
    ], $overrides);
}

it('given a promoter application when required details are submitted then it becomes pending review and a second pending submission conflicts', function () {
    $applicant = User::factory()->create();
    Sanctum::actingAs($applicant);

    $this->postJson('/api/verification/applications', validVerificationPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_review');

    $promoter = Promoter::query()->where('user_id', $applicant->id)->first();
    expect($promoter)->not->toBeNull()
        ->and($promoter->verification_status)->toBe(VerificationStatus::PendingReview);

    // A second submission while the first is still pending must conflict, not queue up.
    $this->postJson('/api/verification/applications', validVerificationPayload())
        ->assertStatus(409);
});

it('given a missing required field when an application is submitted then it returns a field level error', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/verification/applications', validVerificationPayload(['business_name' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_name');
});

it('given an invalid account type when an application is submitted then it returns a field level error', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/verification/applications', validVerificationPayload(['account_type' => 'standard']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('account_type');
});

it('given an application with a supporting document when submitted then it is stored and linked', function () {
    Storage::fake('local');
    Sanctum::actingAs(User::factory()->create());

    $document = UploadedFile::fake()->create('comprovante.pdf', 500, 'application/pdf');

    $this->post('/api/verification/applications', [...validVerificationPayload(), 'document' => $document])
        ->assertCreated();

    $application = VerificationApplication::query()->first();
    expect($application->document_path)->not->toBeNull();
    Storage::disk('local')->assertExists($application->document_path);
});

test('given a never applied account when the verification status is checked then it returns unverified with nulls', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/verification/status')
        ->assertOk()
        ->assertExactJson([
            'status' => 'unverified',
            'rejectionFeedback' => null,
            'latestApplication' => null,
        ]);
});

it('given a submitted application when the verification status is checked then it reflects pending review', function () {
    $applicant = User::factory()->create();
    Sanctum::actingAs($applicant);
    $this->postJson('/api/verification/applications', validVerificationPayload())->assertCreated();

    $this->getJson('/api/verification/status')
        ->assertOk()
        ->assertJsonPath('status', 'pending_review')
        ->assertJsonPath('rejectionFeedback', null);
});

it('given a rejected application when the verification status is checked then feedback is visible and it folds back to unverified', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id, 'verification_status' => VerificationStatus::Unverified]);
    $reviewer = User::factory()->create();
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    $application->reject($reviewer, 'Faltou o link de contato.');
    Sanctum::actingAs($applicant);

    $this->getJson('/api/verification/status')
        ->assertOk()
        ->assertJsonPath('status', 'unverified')
        ->assertJsonPath('rejectionFeedback', 'Faltou o link de contato.');
});

it('given a revoked verified account when the verification status is checked then the revocation reason is visible', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create([
        'user_id' => $applicant->id,
        'verification_status' => VerificationStatus::Unverified,
        'revocation_reason' => 'Documentação vencida, necessário reenviar comprovantes atualizados.',
    ]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::Verified,
    ]);
    Sanctum::actingAs($applicant);

    $this->getJson('/api/verification/status')
        ->assertOk()
        ->assertJsonPath('status', 'unverified')
        ->assertJsonPath('rejectionFeedback', 'Documentação vencida, necessário reenviar comprovantes atualizados.');
});

test('given no Sanctum token when verification endpoints are called then it returns unauthorized', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'post applications' => ['POST', '/api/verification/applications'],
    'get status' => ['GET', '/api/verification/status'],
]);
