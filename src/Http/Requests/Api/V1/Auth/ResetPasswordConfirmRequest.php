<?php

namespace QOR\App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordConfirmRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'E-mail inválido.',
            'token.required' => 'Link de redefinição inválido.',
            'password.required' => 'A nova senha é obrigatória.',
        ];
    }
}
