<?php

namespace QOR\App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTE: this trusts the client-supplied google_id/email/name as-is —
     * verifying the Google ID token server-side (e.g. via Google's token
     * verification library) is a known gap, not implemented in this phase.
     * See the PR description.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'google_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'profile_picture_url' => ['nullable', 'url'],
            'terms_accepted' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_id.required' => 'Token do Google inválido.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'E-mail inválido.',
            'name.required' => 'O nome é obrigatório.',
        ];
    }
}
