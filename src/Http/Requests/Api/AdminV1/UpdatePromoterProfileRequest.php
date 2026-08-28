<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromoterProfileRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'contact_phone.max' => 'O telefone de contato não pode ter mais de 30 caracteres.',
            'contact_email.email' => 'E-mail de contato inválido.',
            'instagram.max' => 'O Instagram não pode ter mais de 255 caracteres.',
            'tiktok.max' => 'O TikTok não pode ter mais de 255 caracteres.',
        ];
    }
}
