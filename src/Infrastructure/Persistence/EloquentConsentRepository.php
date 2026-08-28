<?php

namespace QOR\App\Infrastructure\Persistence;

use Illuminate\Support\Carbon;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Infrastructure\Persistence\Eloquent\ConsentRecordModel;

class EloquentConsentRepository implements ConsentRepository
{
    public function record(
        ConsentableType $consentableType,
        int $consentableId,
        ConsentType $consentType,
        string $policyVersion,
    ): ConsentRecord {
        $model = ConsentRecordModel::create([
            'consentable_type' => $consentableType->value,
            'consentable_id' => $consentableId,
            'consent_type' => $consentType->value,
            'policy_version' => $policyVersion,
            'accepted_at' => now(),
        ]);

        return $this->toDomain($model);
    }

    public function hasAccepted(ConsentableType $consentableType, int $consentableId, ConsentType $consentType): bool
    {
        $latest = $this->latestQuery($consentableType, $consentableId, $consentType)->first();

        return $latest !== null && $latest->revoked_at === null;
    }

    public function revoke(ConsentableType $consentableType, int $consentableId, ConsentType $consentType): void
    {
        $latest = $this->latestQuery($consentableType, $consentableId, $consentType)->first();

        if ($latest !== null && $latest->revoked_at === null) {
            $latest->revoked_at = Carbon::now();
            $latest->save();
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ConsentRecordModel>
     */
    private function latestQuery(ConsentableType $consentableType, int $consentableId, ConsentType $consentType): \Illuminate\Database\Eloquent\Builder
    {
        return ConsentRecordModel::query()
            ->where('consentable_type', $consentableType->value)
            ->where('consentable_id', $consentableId)
            ->where('consent_type', $consentType->value)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id');
    }

    private function toDomain(ConsentRecordModel $model): ConsentRecord
    {
        return new ConsentRecord(
            id: $model->id,
            consentableType: $model->consentable_type,
            consentableId: $model->consentable_id,
            consentType: $model->consent_type,
            policyVersion: $model->policy_version,
            acceptedAt: $model->accepted_at->toDateTimeImmutable(),
            revokedAt: $model->revoked_at?->toDateTimeImmutable(),
        );
    }
}
