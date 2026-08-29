<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFanPreferencesRequest extends FormRequest
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
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
            'radius_km' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'genre_ids.array' => 'Os gêneros informados são inválidos.',
            'genre_ids.*.integer' => 'Gênero inválido.',
            'genre_ids.*.exists' => 'Gênero inválido.',
            'radius_km.integer' => 'O raio de busca deve ser um número inteiro.',
            'radius_km.min' => 'O raio de busca deve ser de pelo menos 1 km.',
            'radius_km.max' => 'O raio de busca deve ser de no máximo 100 km.',
        ];
    }

    public function hasGenreIds(): bool
    {
        return $this->has('genre_ids');
    }

    /**
     * @return list<int>
     */
    public function genreIds(): array
    {
        /** @var list<int|string> $value */
        $value = $this->validated('genre_ids', []);

        return array_map(fn (int|string $id) => (int) $id, $value);
    }

    public function hasRadiusKm(): bool
    {
        return $this->has('radius_km');
    }

    public function radiusKm(): ?int
    {
        /** @var int|string|null $value */
        $value = $this->validated('radius_km');

        return $value !== null ? (int) $value : null;
    }
}
