<?php

namespace QOR\App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterFanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'birthdate' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'E-mail inválido.',
            'password.required' => 'A senha é obrigatória.',
            'birthdate.required' => 'A data de nascimento é obrigatória.',
            'birthdate.date' => 'Data de nascimento inválida.',
            'terms_accepted.required' => 'É necessário aceitar os termos de uso.',
            'terms_accepted.accepted' => 'É necessário aceitar os termos de uso.',
        ];
    }
}
