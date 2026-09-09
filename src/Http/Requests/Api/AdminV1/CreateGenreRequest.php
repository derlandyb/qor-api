<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGenreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('genres', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do gênero é obrigatório.',
            'name.max' => 'O nome do gênero não pode ter mais de 255 caracteres.',
            'name.unique' => 'Já existe um gênero com esse nome.',
        ];
    }
}
