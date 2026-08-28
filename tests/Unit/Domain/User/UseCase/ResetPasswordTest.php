<?php

namespace Tests\Unit\Domain\User\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\PasswordResetPort;
use QOR\App\Domain\User\UseCase\ResetPassword;

class ResetPasswordTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function policy(): PasswordPolicy
    {
        return new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);
    }

    private function passthroughHasher(): PasswordHasher
    {
        $hasher = Mockery::mock(PasswordHasher::class);
        $hasher->shouldReceive('hash')->andReturnUsing(fn (string $plain) => "hashed:{$plain}");

        return $hasher;
    }

    public function test_GIVEN_any_email_WHEN_requesting_a_reset_THEN_it_delegates_to_the_port_once(): void
    {
        $port = Mockery::mock(PasswordResetPort::class);
        $port->shouldReceive('sendResetLink')->once()->with('ana@example.com');

        (new ResetPassword($port, $this->passthroughHasher(), $this->policy()))->requestReset('ana@example.com');
    }

    public function test_GIVEN_a_valid_token_and_strong_password_WHEN_confirming_THEN_the_port_receives_the_hashed_password(): void
    {
        $port = Mockery::mock(PasswordResetPort::class);
        $port->shouldReceive('reset')
            ->once()
            ->with('ana@example.com', 'valid-token', 'hashed:Senha123')
            ->andReturn(true);

        (new ResetPassword($port, $this->passthroughHasher(), $this->policy()))
            ->confirmReset('ana@example.com', 'valid-token', 'Senha123');

        $this->assertTrue(true);
    }

    public function test_GIVEN_a_weak_new_password_WHEN_confirming_THEN_it_rejects_before_the_port_is_called(): void
    {
        $port = Mockery::mock(PasswordResetPort::class);
        $port->shouldNotReceive('reset');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mínimo');

        (new ResetPassword($port, $this->passthroughHasher(), $this->policy()))
            ->confirmReset('ana@example.com', 'valid-token', 'abc');
    }

    public function test_GIVEN_the_port_reports_an_invalid_or_expired_token_WHEN_confirming_THEN_it_surfaces_the_expired_link_message(): void
    {
        $port = Mockery::mock(PasswordResetPort::class);
        $port->shouldReceive('reset')->once()->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link expirado ou já utilizado');

        (new ResetPassword($port, $this->passthroughHasher(), $this->policy()))
            ->confirmReset('ana@example.com', 'stale-token', 'Senha123');
    }
}
