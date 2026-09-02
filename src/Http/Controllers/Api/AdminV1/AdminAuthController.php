<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\Enum\AccountType;
use QOR\App\Domain\Admin\Exception\InvalidCredentials;
use QOR\App\Domain\Admin\UseCase\AuthenticateAdmin;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\LoginRequest;
use QOR\App\Http\Support\OrganizerIdentityResolver;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AuthenticateAdmin $authenticateAdmin,
        private readonly OrganizerIdentityResolver $organizerIdentityResolver,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $account = $this->authenticateAdmin->executeWithPassword(
                $this->field($request, 'email'),
                $this->field($request, 'password'),
            );
        } catch (InvalidCredentials $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json($this->sessionResponse($account));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        $admin->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        $account = new AdminAccount(
            id: (int) $admin->id,
            name: $admin->name,
            email: $admin->email,
            passwordHash: $admin->password,
            isSuperAdmin: (bool) $admin->is_super_admin,
        );

        return response()->json([
            'data' => $this->accountToArray($account, $this->accountTypeFor($account, $admin)->value),
        ]);
    }

    private function field(FormRequest $request, string $key): string
    {
        /** @var string $value */
        $value = $request->validated($key);

        return $value;
    }

    /**
     * @return array{data: array<string, mixed>, token: string}
     */
    private function sessionResponse(AdminAccount $account): array
    {
        $model = AdminUserModel::findOrFail($account->id);

        // The actual credential qor-admin's SPA uses (ARCHITECTURE §2 —
        // cookie mode, never a client-held token): without this, every
        // subsequent cookie-based request (GET /me, the approval queues,
        // ...) is unauthenticated in a real browser, even though login()
        // itself reports success — the `token` below is never read by the
        // admin client and was, until now, the only thing this endpoint
        // actually authenticated.
        //
        // Sanctum's custom 'admin' guard (config/auth.php) is a stateless
        // RequestGuard — it has no ->login(). config/sanctum.php's 'guard'
        // list names the real session-backed guard(s) Sanctum actually
        // checks for a stateful (cookie) request, regardless of which
        // named guard a route declares — 'admin-session' here.
        Auth::guard('admin-session')->login($model);

        $token = $model->createToken('admin')->plainTextToken;

        return ['data' => $this->accountToArray($account), 'token' => $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountToArray(AdminAccount $account, ?string $accountType = null): array
    {
        $data = [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'permissions' => $this->permissionsFor($account),
        ];

        if ($accountType !== null) {
            $data['account_type'] = $accountType;
        }

        return $data;
    }

    private function accountTypeFor(AdminAccount $account, AdminUserModel $admin): AccountType
    {
        if ($account->isSuperAdmin) {
            return AccountType::SuperAdmin;
        }

        [$createdByType] = $this->organizerIdentityResolver->resolve($admin);

        return match ($createdByType) {
            EventCreatedByType::VenueAdmin => AccountType::VenueAdmin,
            EventCreatedByType::Promoter => AccountType::Promoter,
        };
    }

    /**
     * UI hint only — every capability implied here is independently
     * re-enforced server-side by `guard.super-admin` (EnsureSuperAdmin),
     * which re-reads the flag from the database on each request. Never
     * make an authorization decision based on this list alone.
     *
     * @return list<string>
     */
    private function permissionsFor(AdminAccount $account): array
    {
        return $account->isSuperAdmin ? ['approvals.manage', 'plans.manage'] : [];
    }
}
