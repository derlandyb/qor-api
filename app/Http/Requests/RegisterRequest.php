<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', self::passwordStrengthRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // Laravel's default `unique` message ("The email has already been taken.") renders in
        // English inside an otherwise fully pt-BR UI.
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
        ];
    }

    /**
     * The single source of truth for "what counts as a strong enough password" — reused by
     * admin-auth's PasswordChangeController and staff temp-password generation rather than
     * being reimplemented.
     */
    public static function passwordStrengthRule(): Password
    {
        return Password::min(8)->letters()->numbers();
    }
}
