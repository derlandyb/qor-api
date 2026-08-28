<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class EditEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * All fields are optional (partial edit) — business-rule enforcement
     * of which fields may actually change per event status lives in
     * src/Domain/Event/UseCase/EditEvent.php, not here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'city' => ['nullable', Rule::enum(City::class)],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'is_free' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:500'],
            'ticket_url' => ['nullable', 'url'],
            'capacity' => ['nullable', 'integer'],
            'age_rating' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'file', 'image'],
            'promoter_ids' => ['nullable', 'array'],
            'promoter_ids.*' => ['integer', 'exists:promoters,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'starts_at.date' => 'Data de início inválida.',
            'city.enum' => 'Cidade inválida.',
            'genre_id.integer' => 'Gênero inválido.',
            'genre_id.exists' => 'Gênero inválido.',
            'is_free.boolean' => 'Valor inválido para evento gratuito.',
            'address.max' => 'O endereço não pode ter mais de 500 caracteres.',
            'ticket_url.url' => 'Link de ingresso inválido.',
            'capacity.integer' => 'Capacidade inválida.',
            'cover_image.file' => 'Envie um arquivo de imagem.',
            'cover_image.image' => 'Envie um arquivo de imagem.',
            'promoter_ids.array' => 'Lista de promotores inválida.',
            'promoter_ids.*.integer' => 'Um dos promotores selecionados é inválido.',
            'promoter_ids.*.exists' => 'Um dos promotores selecionados é inválido.',
        ];
    }
}
