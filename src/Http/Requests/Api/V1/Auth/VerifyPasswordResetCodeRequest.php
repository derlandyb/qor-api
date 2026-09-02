<?php

namespace QOR\App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetCodeRequest extends FormRequest
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
        /** @var int $otpLength */
        $otpLength = config('qor.auth.otp_length');

        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:'.$otpLength],
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
            'code.required' => 'O código é obrigatório.',
            'code.digits' => 'Código inválido.',
        ];
    }
}
