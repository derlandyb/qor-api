<?php

namespace QOR\App\Http\Requests\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['nullable', 'numeric', 'min:0'],
            'publish_quota' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do plano é obrigatório.',
            'name.max' => 'O nome do plano não pode ter mais de 255 caracteres.',
            'monthly_price.required' => 'O preço mensal é obrigatório.',
            'monthly_price.numeric' => 'O preço mensal deve ser um número.',
            'monthly_price.min' => 'O preço mensal não pode ser negativo.',
            'annual_price.numeric' => 'O preço anual deve ser um número.',
            'annual_price.min' => 'O preço anual não pode ser negativo.',
            'publish_quota.required' => 'A cota de publicações é obrigatória.',
            'publish_quota.integer' => 'A cota de publicações deve ser um número inteiro.',
            'publish_quota.min' => 'A cota de publicações não pode ser negativa.',
        ];
    }
}
