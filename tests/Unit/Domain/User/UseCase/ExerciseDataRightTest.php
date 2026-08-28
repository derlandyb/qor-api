<?php

namespace Tests\Unit\Domain\User\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\ExerciseDataRight;
use QOR\App\Domain\User\UserRepository;

class ExerciseDataRightTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function user(): User
    {
        return new User(
            id: 1,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-06-15'),
            passwordHash: 'hash',
        );
    }

    public function test_GIVEN_an_existing_user_WHEN_accessing_data_THEN_it_returns_a_full_readable_summary_with_consents(): void
    {
        $consentRecord = new ConsentRecord(
            id: 1,
            consentableType: ConsentableType::User,
            consentableId: 1,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable('2026-01-01'),
        );

        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(1)->andReturn($this->user());

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('findAllForConsentable')->once()
            ->with(ConsentableType::User, 1)->andReturn([$consentRecord]);

        $summary = (new ExerciseDataRight($users, $consent))->access(1);

        $this->assertSame('ana@example.com', $summary['email']);
        $this->assertCount(1, $summary['consents']);
        $this->assertSame('terms', $summary['consents'][0]['type']);
    }

    public function test_GIVEN_an_existing_user_WHEN_exporting_data_THEN_it_returns_a_portable_json_payload(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn($this->user());

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('findAllForConsentable')->once()->andReturn([]);

        $export = (new ExerciseDataRight($users, $consent))->export(1);

        $decoded = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ana@example.com', $decoded['email']);
    }

    public function test_GIVEN_an_existing_user_WHEN_deleting_THEN_the_repository_deletes_it(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(1)->andReturn($this->user());
        $users->shouldReceive('delete')->once()->with(1);

        $consent = Mockery::mock(ConsentRepository::class);

        (new ExerciseDataRight($users, $consent))->delete(1);
    }

    public function test_GIVEN_a_nonexistent_user_WHEN_deleting_THEN_it_throws_and_never_deletes(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $users->shouldNotReceive('delete');

        $consent = Mockery::mock(ConsentRepository::class);

        $this->expectException(\InvalidArgumentException::class);

        (new ExerciseDataRight($users, $consent))->delete(999);
    }

    public function test_GIVEN_an_existing_user_WHEN_revoking_consent_THEN_it_stops_immediately_without_deleting_the_account(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(1)->andReturn($this->user());
        $users->shouldNotReceive('delete');

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('revoke')->once()->with(ConsentableType::User, 1, ConsentType::Location);

        (new ExerciseDataRight($users, $consent))->revokeConsent(1, ConsentType::Location);
    }
}
