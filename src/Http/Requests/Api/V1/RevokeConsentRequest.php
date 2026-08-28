<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\User\Enum\ConsentType;

class RevokeConsentRequest extends FormRequest
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
            'consent_type' => ['required', Rule::enum(ConsentType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_type.required' => 'O tipo de consentimento é obrigatório.',
            'consent_type.enum' => 'Tipo de consentimento inválido.',
        ];
    }

    public function consentType(): ConsentType
    {
        /** @var string $value */
        $value = $this->validated('consent_type');

        return ConsentType::from($value);
    }
}
