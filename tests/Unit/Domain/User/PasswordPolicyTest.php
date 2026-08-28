<?php

namespace Tests\Unit\Domain\User;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\PasswordPolicy;

class PasswordPolicyTest extends TestCase
{
    private function policy(): PasswordPolicy
    {
        return new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);
    }

    public function test_GIVEN_a_password_meeting_every_rule_WHEN_validating_THEN_there_are_no_errors(): void
    {
        $this->assertSame([], $this->policy()->validate('Senha123'));
    }

    public function test_GIVEN_a_password_shorter_than_the_minimum_WHEN_validating_THEN_it_reports_the_length_reason(): void
    {
        $errors = $this->policy()->validate('Ab1');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('mínimo', $errors[0]);
    }

    public function test_GIVEN_a_password_without_mixed_case_WHEN_validating_THEN_it_reports_the_case_reason(): void
    {
        $errors = $this->policy()->validate('senha123');

        $this->assertContains('A senha precisa conter letras maiúsculas e minúsculas.', $errors);
    }

    public function test_GIVEN_a_password_without_numbers_WHEN_validating_THEN_it_reports_the_numbers_reason(): void
    {
        $errors = $this->policy()->validate('SenhaForte');

        $this->assertContains('A senha precisa conter pelo menos um número.', $errors);
    }

    public function test_GIVEN_a_password_failing_multiple_rules_WHEN_validating_THEN_all_reasons_are_reported(): void
    {
        $errors = $this->policy()->validate('abc');

        $this->assertCount(3, $errors);
    }
}
