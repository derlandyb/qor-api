<?php

namespace App\Http\Resources;

use App\Models\VerificationApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VerificationApplication */
final class VerificationApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accountType' => $this->account_type->value,
            'businessName' => $this->business_name,
            'contactEmail' => $this->contact_email,
            'contactPhone' => $this->contact_phone,
            'socialLink' => $this->social_link,
            'documentPath' => $this->document_path,
            'status' => $this->status->value,
            'rejectionFeedback' => $this->rejection_feedback,
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
