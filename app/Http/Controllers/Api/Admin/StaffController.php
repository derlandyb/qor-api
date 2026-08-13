<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateStaffRequest;
use App\Models\User;
use App\Notifications\NewStaffAccountCredentials;
use App\Support\TempPasswordGenerator;
use Illuminate\Http\JsonResponse;

final class StaffController extends Controller
{
    // auth:sanctum + can:super-admin on both actions (route middleware) — the one path that
    // grows the moderation team without direct Postgres access (ADMIN-AUTH-003).
    public function store(CreateStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $temporaryPassword = TempPasswordGenerator::generate();

        $staff = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $temporaryPassword,
            'role' => Role::Admin,
            'must_change_password' => true,
        ]);

        $staff->notify(new NewStaffAccountCredentials($temporaryPassword));

        // Deliberately only these four fields — the temp password is never returned here,
        // it only ever reaches the new account via the notification above.
        return response()->json([
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role->value,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $staff = User::query()
            ->whereIn('role', [Role::Admin, Role::SuperAdmin])
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'createdAt' => $user->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $staff]);
    }
}
