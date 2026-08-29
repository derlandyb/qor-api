<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
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
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'silence_all' => ['sometimes', 'boolean'],
            'trigger_nearby_reminder' => ['sometimes', 'boolean'],
            'trigger_event_changed_cancelled' => ['sometimes', 'boolean'],
            'trigger_friend_interest' => ['sometimes', 'boolean'],
            'trigger_new_regional' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.boolean' => 'O valor informado deve ser verdadeiro ou falso.',
        ];
    }
}
