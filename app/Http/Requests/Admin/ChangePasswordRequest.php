<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\RegisterRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

final class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', RegisterRequest::passwordStrengthRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user === null || $user->password === null || ! Hash::check($this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', 'A senha atual informada está incorreta.');
            }
        });
    }
}
