<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
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
            'outcome' => ['required', 'string', 'in:approved,rejected,suspended,suspension_lifted'],
            'reason' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outcome.required' => 'O resultado da decisão é obrigatório.',
            'outcome.in' => 'Resultado de decisão inválido.',
            'reason.string' => 'A justificativa deve ser um texto.',
        ];
    }
}
