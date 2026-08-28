<?php

namespace Tests\Unit\Domain\User\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\UpdateProfile;
use QOR\App\Domain\User\UserRepository;

class UpdateProfileTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function existingUser(): User
    {
        return new User(
            id: 1,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('1995-01-01'),
            passwordHash: 'hash',
            phone: '27999990000',
            emailVerifiedAt: new DateTimeImmutable(),
        );
    }

    public function test_GIVEN_a_name_and_phone_change_WHEN_updating_THEN_they_persist_immediately(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(1)->andReturn($this->existingUser());
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => $user);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldNotReceive('sendForPendingEmail');

        $updated = (new UpdateProfile($users, $fileUpload, $emailVerification))
            ->execute(userId: 1, name: 'Ana Costa', phone: '27988887777');

        $this->assertSame('Ana Costa', $updated->name);
        $this->assertSame('27988887777', $updated->phone);
    }

    public function test_GIVEN_a_picture_upload_WHEN_updating_THEN_the_file_upload_port_is_used(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn($this->existingUser());
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => $user);

        $picture = new UploadableFile('/tmp/pic.jpg', 'pic.jpg', 'image/jpeg', 1024, 500, 500);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldReceive('upload')->once()->with($picture, 'profile-pictures')->andReturn('https://cdn/pic.jpg');

        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldNotReceive('sendForPendingEmail');

        $updated = (new UpdateProfile($users, $fileUpload, $emailVerification))
            ->execute(userId: 1, picture: $picture);

        $this->assertSame('https://cdn/pic.jpg', $updated->profilePictureUrl);
    }

    public function test_GIVEN_a_new_email_WHEN_updating_THEN_the_old_email_stays_active_and_verification_is_sent(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn($this->existingUser());
        $users->shouldReceive('findByEmail')->once()->with('novo@example.com')->andReturn(null);
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => $user);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldReceive('sendForPendingEmail')->once()->with(1);

        $updated = (new UpdateProfile($users, $fileUpload, $emailVerification))
            ->execute(userId: 1, newEmail: 'novo@example.com');

        $this->assertSame('ana@example.com', $updated->email);
        $this->assertSame('novo@example.com', $updated->pendingEmail);
    }

    public function test_GIVEN_the_new_email_matches_the_current_email_WHEN_updating_THEN_no_verification_is_triggered(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn($this->existingUser());
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => $user);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldNotReceive('sendForPendingEmail');

        (new UpdateProfile($users, $fileUpload, $emailVerification))
            ->execute(userId: 1, newEmail: 'ana@example.com');
    }

    public function test_GIVEN_the_new_email_is_already_registered_WHEN_updating_THEN_it_is_rejected(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn($this->existingUser());
        $users->shouldReceive('findByEmail')->once()->with('outro@example.com')->andReturn($this->existingUser());
        $users->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $emailVerification = Mockery::mock(EmailVerificationPort::class);

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateProfile($users, $fileUpload, $emailVerification))
            ->execute(userId: 1, newEmail: 'outro@example.com');
    }

    public function test_GIVEN_a_nonexistent_user_id_WHEN_updating_THEN_it_throws(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(999)->andReturn(null);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $emailVerification = Mockery::mock(EmailVerificationPort::class);

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateProfile($users, $fileUpload, $emailVerification))->execute(userId: 999);
    }
}
