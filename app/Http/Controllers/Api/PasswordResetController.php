<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

final class PasswordResetController extends Controller
{
    private const NEUTRAL_MESSAGE = 'Se este e-mail estiver cadastrado, enviaremos um link de redefinição de senha.';

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // Status is intentionally ignored in the response — surfacing it would leak whether
        // the email is a registered account.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => self::NEUTRAL_MESSAGE]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Token de redefinição inválido ou expirado.',
            ], 422);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
