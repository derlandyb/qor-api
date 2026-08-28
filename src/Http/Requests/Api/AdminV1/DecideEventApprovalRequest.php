<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;

class DecideEventApprovalRequest extends FormRequest
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
            'outcome' => ['required', 'string', 'in:approved,rejected,force_cancelled'],
            'feedback' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outcome.required' => 'A decisão é obrigatória.',
            'outcome.in' => 'Decisão inválida.',
            'feedback.string' => 'O feedback deve ser um texto.',
        ];
    }
}
