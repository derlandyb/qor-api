<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerificationApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_type', 'promoter_id', 'venue_id',
        'business_name', 'contact_email', 'contact_phone', 'social_link',
        'document_path', 'status', 'rejection_feedback', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'account_type' => AccountType::class,
        'status' => VerificationApplicationStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function approve(User $reviewer): void
    {
        abort_unless($this->status === VerificationApplicationStatus::PendingReview, 409);

        $this->update([
            'status' => VerificationApplicationStatus::Verified,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->applicantProfile()->update(['verification_status' => VerificationStatus::Verified]);
    }

    public function reject(User $reviewer, string $feedback): void
    {
        abort_unless($this->status === VerificationApplicationStatus::PendingReview, 409);

        $this->update([
            'status' => VerificationApplicationStatus::Rejected,
            'rejection_feedback' => $feedback,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        // Not a "rejected" account status — VerificationStatus has no such case; folds back to Unverified.
        $this->applicantProfile()->update(['verification_status' => VerificationStatus::Unverified]);
    }

    /** The promoter or venue this application belongs to — exactly one of the two is set. */
    private function applicantProfile(): Promoter|Venue
    {
        return $this->promoter ?? $this->venue;
    }
}
