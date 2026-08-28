<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPromoterRequest extends FormRequest
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
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_email' => ['required', 'email'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'registration_email' => ['required', 'email'],
            'password' => ['required', 'string'],
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
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'contact_phone.required' => 'O telefone de contato é obrigatório.',
            'contact_phone.max' => 'O telefone de contato não pode ter mais de 30 caracteres.',
            'contact_email.required' => 'O e-mail de contato é obrigatório.',
            'contact_email.email' => 'E-mail de contato inválido.',
            'instagram.max' => 'O Instagram não pode ter mais de 255 caracteres.',
            'tiktok.max' => 'O TikTok não pode ter mais de 255 caracteres.',
            'registration_email.required' => 'O e-mail de cadastro é obrigatório.',
            'registration_email.email' => 'E-mail de cadastro inválido.',
            'password.required' => 'A senha é obrigatória.',
            'terms_accepted.required' => 'É necessário aceitar os termos de uso.',
            'terms_accepted.accepted' => 'É necessário aceitar os termos de uso.',
        ];
    }
}
