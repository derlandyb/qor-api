<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class UpdateAddressRequest extends FormRequest
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
            'city' => ['required', Rule::enum(City::class)],
            'state' => ['required', 'string', 'max:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.required' => 'A cidade é obrigatória.',
            'city.enum' => 'Cidade inválida.',
            'state.required' => 'O estado é obrigatório.',
        ];
    }

    public function city(): City
    {
        /** @var string $value */
        $value = $this->validated('city');

        return City::from($value);
    }

    public function state(): string
    {
        /** @var string $value */
        $value = $this->validated('state');

        return $value;
    }

    public function street(): ?string
    {
        /** @var string|null $value */
        $value = $this->validated('street');

        return $value;
    }

    public function number(): ?string
    {
        /** @var string|null $value */
        $value = $this->validated('number');

        return $value;
    }

    public function complement(): ?string
    {
        /** @var string|null $value */
        $value = $this->validated('complement');

        return $value;
    }
}
