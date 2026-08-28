<?php

namespace Tests\Unit\Domain\Promoter\UseCase;

use DomainException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Promoter\UseCase\EditPromoterProfile;

class EditPromoterProfileTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function existingPromoter(): Promoter
    {
        return new Promoter(
            id: 1,
            userId: 42,
            name: 'Produtora Vitória Eventos',
            contactPhone: '27999997777',
            contactEmail: 'contato@produtora.com',
            approvalStatus: ApprovalStatus::Approved,
            instagram: '@produtoravitoria',
            tiktok: '@produtoravitoria',
        );
    }

    public function test_GIVEN_only_a_name_change_WHEN_editing_THEN_other_fields_remain_unchanged(): void
    {
        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(1)->andReturn($this->existingPromoter());
        $promoters->shouldReceive('save')->once()->andReturnUsing(fn (Promoter $promoter) => $promoter);

        $updated = (new EditPromoterProfile($promoters))
            ->execute(promoterId: 1, name: 'Produtora Vitória Eventos LTDA');

        $this->assertSame('Produtora Vitória Eventos LTDA', $updated->name);
        $this->assertSame('27999997777', $updated->contactPhone);
        $this->assertSame('contato@produtora.com', $updated->contactEmail);
        $this->assertSame('@produtoravitoria', $updated->instagram);
        $this->assertSame('@produtoravitoria', $updated->tiktok);
        $this->assertSame(ApprovalStatus::Approved, $updated->approvalStatus);
    }

    public function test_GIVEN_new_social_handles_WHEN_editing_THEN_they_are_persisted(): void
    {
        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(1)->andReturn($this->existingPromoter());
        $promoters->shouldReceive('save')->once()->andReturnUsing(fn (Promoter $promoter) => $promoter);

        $updated = (new EditPromoterProfile($promoters))
            ->execute(promoterId: 1, instagram: '@novaconta', tiktok: '@novaconta');

        $this->assertSame('@novaconta', $updated->instagram);
        $this->assertSame('@novaconta', $updated->tiktok);
    }

    public function test_GIVEN_a_nonexistent_promoter_id_WHEN_editing_THEN_it_throws(): void
    {
        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $promoters->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Promoter não encontrado.');

        (new EditPromoterProfile($promoters))->execute(promoterId: 999);
    }

    public function test_GIVEN_a_suspended_promoter_WHEN_editing_THEN_it_throws_and_does_not_save(): void
    {
        $suspended = new Promoter(
            id: 1,
            userId: 42,
            name: 'Produtora Vitória Eventos',
            contactPhone: '27999997777',
            contactEmail: 'contato@produtora.com',
            approvalStatus: ApprovalStatus::Suspended,
        );

        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(1)->andReturn($suspended);
        $promoters->shouldNotReceive('save');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Sua conta está suspensa e não pode editar o perfil.');

        (new EditPromoterProfile($promoters))->execute(promoterId: 1, name: 'Novo Nome');
    }

    public function test_GIVEN_the_approval_status_is_not_a_parameter_WHEN_editing_THEN_the_current_approval_status_is_preserved(): void
    {
        $promoters = Mockery::mock(PromoterRepository::class);
        $promoters->shouldReceive('findById')->once()->with(1)->andReturn($this->existingPromoter());
        $promoters->shouldReceive('save')->once()->andReturnUsing(fn (Promoter $promoter) => $promoter);

        $updated = (new EditPromoterProfile($promoters))
            ->execute(promoterId: 1, contactPhone: '27988887777');

        $this->assertSame(ApprovalStatus::Approved, $updated->approvalStatus);
    }
}
