<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class ListEventsRequest extends FormRequest
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
            'city' => ['nullable', Rule::enum(City::class)],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.enum' => 'Cidade inválida.',
            'genre.integer' => 'Gênero inválido.',
            'genre.exists' => 'Gênero inválido.',
            'cursor.string' => 'Cursor de paginação inválido.',
        ];
    }

    public function city(): ?City
    {
        /** @var string|null $city */
        $city = $this->validated('city');

        return $city !== null ? City::from($city) : null;
    }

    public function genreId(): ?int
    {
        /** @var int|string|null $genre */
        $genre = $this->validated('genre');

        return $genre !== null ? (int) $genre : null;
    }

    public function cursor(): ?string
    {
        /** @var string|null $cursor */
        $cursor = $this->validated('cursor');

        return $cursor;
    }
}
