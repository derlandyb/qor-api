<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Shared\Enum\City;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors the constraints enforced by the Event domain entity's own
     * constructor (title non-empty, paid events require a ticket_url) — see
     * src/Domain/Event/Event.php — plus the request-layer "must actually be
     * an uploaded file" check for cover_image (ARCHITECTURE.md §10).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'city' => ['required', Rule::enum(City::class)],
            'genre_id' => ['required', 'integer', 'exists:genres,id'],
            'is_free' => ['required', 'boolean'],
            'address' => ['nullable', 'string', 'max:500'],
            'ticket_url' => [Rule::requiredIf(fn () => ! $this->boolean('is_free')), 'nullable', 'url'],
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
            'title.required' => 'O título é obrigatório.',
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'description.required' => 'A descrição é obrigatória.',
            'starts_at.required' => 'A data de início é obrigatória.',
            'starts_at.date' => 'Data de início inválida.',
            'city.required' => 'A cidade é obrigatória.',
            'city.enum' => 'Cidade inválida.',
            'genre_id.required' => 'O gênero é obrigatório.',
            'genre_id.integer' => 'Gênero inválido.',
            'genre_id.exists' => 'Gênero inválido.',
            'is_free.required' => 'É necessário informar se o evento é gratuito.',
            'is_free.boolean' => 'Valor inválido para evento gratuito.',
            'address.max' => 'O endereço não pode ter mais de 500 caracteres.',
            'ticket_url.required' => 'Eventos pagos precisam de um link de ingresso.',
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
