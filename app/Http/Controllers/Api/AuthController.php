<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        // Guarded before Hash::check() — a null password (Google-only account) would
        // otherwise crash the comparison instead of failing it cleanly.
        if ($user === null || $user->password === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'As credenciais informadas estão incorretas.',
            ], 401);
        }

        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'mustChangePassword' => $user->must_change_password,
        ]);
    }

    // Bug 1 fix — lets a client rehydrate `user`/`mustChangePassword` from a stored token
    // (e.g. after a hard navigation/reload), in the same shape login() already returns.
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'mustChangePassword' => $user->must_change_password,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->json(null, 204);
    }
}
