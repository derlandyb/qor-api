<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerificationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'in:promoter,venue'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            // Single field per the data model — either an Instagram or WhatsApp link;
            // deliberately no URL/domain validation (reviewer judges validity manually).
            'social_link' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
