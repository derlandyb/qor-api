<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

final class PasswordChangeController extends Controller
{
    // Plain auth:sanctum, any role — this is the one path that must work regardless of
    // must_change_password, since it's how the flag itself gets cleared (design.md).
    public function store(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->validated()['password'],
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Senha atualizada com sucesso.']);
    }
}
