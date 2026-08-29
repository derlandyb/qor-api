<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ShareEventRequest extends FormRequest
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
            'friend_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'friend_user_id.required' => 'O amigo é obrigatório.',
            'friend_user_id.integer' => 'Amigo inválido.',
            'friend_user_id.exists' => 'Amigo não encontrado.',
        ];
    }

    public function friendUserId(): int
    {
        /** @var int|string $value */
        $value = $this->validated('friend_user_id');

        return (int) $value;
    }
}
