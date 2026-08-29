<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendFriendRequestRequest extends FormRequest
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
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_user_id.required' => 'O usuário destinatário é obrigatório.',
            'recipient_user_id.integer' => 'Usuário destinatário inválido.',
            'recipient_user_id.exists' => 'Usuário destinatário não encontrado.',
        ];
    }

    public function recipientUserId(): int
    {
        /** @var int|string $value */
        $value = $this->validated('recipient_user_id');

        return (int) $value;
    }
}
