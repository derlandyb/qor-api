<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

final class GoogleAuthController extends Controller
{
    public function login(GoogleLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');
        $googleUser = $driver->stateless()->userFromToken($validated['id_token']);

        $user = User::query()->where('email', $googleUser->getEmail())->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => null,
            ]);
        } elseif ($user->google_id === null) {
            // Email matched an existing password account — link it. Email is the sole source
            // of truth here; a mismatched Google id on an already-linked account never errors,
            // it simply signs the user in (see the elseif branch not touching google_id below).
            $user->google_id = $googleUser->getId();
            $user->save();
        }

        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }
}
