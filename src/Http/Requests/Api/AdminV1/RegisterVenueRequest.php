<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class RegisterVenueRequest extends FormRequest
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
            'description' => ['required', 'string'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', Rule::enum(City::class)],
            'contact_phone' => ['required', 'string', 'max:30'],
            'contact_email' => ['required', 'email'],
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
            'description.required' => 'A descrição é obrigatória.',
            'address.required' => 'O endereço é obrigatório.',
            'address.max' => 'O endereço não pode ter mais de 500 caracteres.',
            'city.required' => 'A cidade é obrigatória.',
            'city.enum' => 'Cidade inválida.',
            'contact_phone.required' => 'O telefone de contato é obrigatório.',
            'contact_phone.max' => 'O telefone de contato não pode ter mais de 30 caracteres.',
            'contact_email.required' => 'O e-mail de contato é obrigatório.',
            'contact_email.email' => 'E-mail de contato inválido.',
            'registration_email.required' => 'O e-mail de cadastro é obrigatório.',
            'registration_email.email' => 'E-mail de cadastro inválido.',
            'password.required' => 'A senha é obrigatória.',
            'terms_accepted.required' => 'É necessário aceitar os termos de uso.',
            'terms_accepted.accepted' => 'É necessário aceitar os termos de uso.',
        ];
    }
}
