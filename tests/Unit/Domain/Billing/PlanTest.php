<?php

namespace Tests\Unit\Domain\Billing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;

class PlanTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_the_plan_is_created(): void
    {
        $plan = new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: 499.0,
            publishQuota: 5,
        );

        $this->assertSame('Básico', $plan->name);
    }

    public function test_GIVEN_an_empty_name_WHEN_constructing_THEN_it_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O nome do plano não pode ser vazio.');

        new Plan(
            id: 1,
            name: '',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
        );
    }

    public function test_GIVEN_a_negative_monthly_price_WHEN_constructing_THEN_it_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O preço mensal do plano não pode ser negativo.');

        new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: -1,
            annualPrice: null,
            publishQuota: 5,
        );
    }

    public function test_GIVEN_a_negative_annual_price_WHEN_constructing_THEN_it_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O preço anual do plano não pode ser negativo.');

        new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: -10,
            publishQuota: 5,
        );
    }

    public function test_GIVEN_a_null_annual_price_WHEN_constructing_THEN_it_does_not_throw(): void
    {
        $plan = new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
        );

        $this->assertNull($plan->annualPrice);
    }

    public function test_GIVEN_a_negative_publish_quota_WHEN_constructing_THEN_it_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A cota de publicações do plano não pode ser negativa.');

        new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: -1,
        );
    }

    public function test_GIVEN_a_null_publish_quota_WHEN_constructing_THEN_it_does_not_throw(): void
    {
        $plan = new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: null,
        );

        $this->assertNull($plan->publishQuota);
    }

    public function test_GIVEN_a_zero_monthly_price_WHEN_constructing_THEN_it_does_not_throw(): void
    {
        $plan = new Plan(
            id: 1,
            name: 'Gratuito',
            monthlyPrice: 0,
            annualPrice: null,
            publishQuota: 5,
        );

        $this->assertSame(0.0, $plan->monthlyPrice);
    }
}
