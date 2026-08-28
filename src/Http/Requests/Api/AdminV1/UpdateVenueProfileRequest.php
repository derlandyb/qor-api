<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class UpdateVenueProfileRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', Rule::enum(City::class)],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email'],
            'image' => ['nullable', 'image'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'address.max' => 'O endereço não pode ter mais de 500 caracteres.',
            'city.enum' => 'Cidade inválida.',
            'contact_phone.max' => 'O telefone de contato não pode ter mais de 30 caracteres.',
            'contact_email.email' => 'E-mail de contato inválido.',
            'image.image' => 'O arquivo enviado precisa ser uma imagem.',
        ];
    }
}
