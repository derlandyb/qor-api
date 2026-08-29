<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\Exception\InvalidCredentials;
use QOR\App\Domain\Admin\UseCase\AuthenticateAdmin;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\LoginRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AuthenticateAdmin $authenticateAdmin,
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
        $token = $model->createToken('admin')->plainTextToken;

        return ['data' => $this->accountToArray($account), 'token' => $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountToArray(AdminAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'is_super_admin' => $account->isSuperAdmin,
        ];
    }
}
