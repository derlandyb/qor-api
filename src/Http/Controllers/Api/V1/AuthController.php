<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\Exception\InvalidCredentials;
use QOR\App\Domain\User\Exception\UnverifiedAccount;
use QOR\App\Domain\User\OtpVerificationPort;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\AuthenticateFan;
use QOR\App\Domain\User\UseCase\RegisterFan;
use QOR\App\Domain\User\UseCase\ResetPassword;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use QOR\App\Http\Requests\Api\V1\Auth\GoogleAuthRequest;
use QOR\App\Http\Requests\Api\V1\Auth\LoginRequest;
use QOR\App\Http\Requests\Api\V1\Auth\RegisterFanRequest;
use QOR\App\Http\Requests\Api\V1\Auth\ResendVerificationRequest;
use QOR\App\Http\Requests\Api\V1\Auth\ResetPasswordConfirmRequest;
use QOR\App\Http\Requests\Api\V1\Auth\VerifyEmailCodeRequest;
use QOR\App\Http\Requests\Api\V1\Auth\VerifyPasswordResetCodeRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterFan $registerFan,
        private readonly AuthenticateFan $authenticateFan,
        private readonly ResetPassword $resetPassword,
        private readonly EmailVerificationPort $emailVerification,
        private readonly OtpVerificationPort $otpVerification,
        private readonly UserRepository $users,
    ) {
    }

    public function register(RegisterFanRequest $request): JsonResponse
    {
        /** @var string $policyVersion */
        $policyVersion = config('qor.legal.policy_version');

        $user = $this->registerFan->execute(
            name: $this->field($request, 'name'),
            email: $this->field($request, 'email'),
            password: $this->field($request, 'password'),
            birthdate: new \DateTimeImmutable($this->field($request, 'birthdate')),
            phone: $this->nullableField($request, 'phone'),
            consentPolicyVersion: $policyVersion,
        );

        return response()->json(['data' => $this->userToArray($user)], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->authenticateFan->executeWithPassword(
                $this->field($request, 'email'),
                $this->field($request, 'password'),
            );
        } catch (UnverifiedAccount $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidCredentials $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json($this->sessionResponse($user));
    }

    public function google(GoogleAuthRequest $request): JsonResponse
    {
        /** @var string $policyVersion */
        $policyVersion = config('qor.legal.policy_version');
        $termsAccepted = (bool) $request->validated('terms_accepted');

        $user = $this->authenticateFan->executeWithGoogle(
            googleEmail: $this->field($request, 'email'),
            googleId: $this->field($request, 'google_id'),
            name: $this->field($request, 'name'),
            profilePictureUrl: $this->nullableField($request, 'profile_picture_url'),
            consentPolicyVersion: $termsAccepted ? $policyVersion : null,
        );

        return response()->json($this->sessionResponse($user));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        // A session-cookie request (the only credential qor-website's SPA
        // actually presents) resolves currentAccessToken() to Sanctum's
        // TransientToken, which has no delete() — only a genuine bearer-
        // token request (mobile) has a real PersonalAccessToken row to
        // revoke. HasApiTokens' PHPDoc claims this always returns
        // PersonalAccessToken, which is wrong at runtime for a session
        // request — hence the ignore.
        $token = $user->currentAccessToken();
        // @phpstan-ignore instanceof.alwaysTrue
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        Auth::guard('fan-session')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->resetPassword->requestReset($this->field($request, 'email'));

        return response()->json(['message' => 'Se este e-mail existir, você receberá um código.']);
    }

    public function resetPasswordConfirm(ResetPasswordConfirmRequest $request): JsonResponse
    {
        $this->resetPassword->confirmReset(
            $this->field($request, 'email'),
            $this->field($request, 'token'),
            $this->field($request, 'password'),
        );

        return response()->json(['message' => 'Senha atualizada com sucesso.']);
    }

    public function verifyEmail(int $id, string $hash): JsonResponse
    {
        if (! $this->emailVerification->verify($id, $hash)) {
            return response()->json(['message' => 'Link de verificação inválido ou expirado.'], 404);
        }

        return response()->json(['message' => 'E-mail verificado com sucesso.']);
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $this->otpVerification->issueEmailVerificationCode($this->field($request, 'email'));

        return response()->json([
            'message' => 'Se este e-mail existir e ainda não tiver sido verificado, você receberá um novo código.',
        ]);
    }

    public function verifyEmailCode(VerifyEmailCodeRequest $request): JsonResponse
    {
        $verified = $this->otpVerification->verifyEmailCode(
            $this->field($request, 'email'),
            $this->field($request, 'code'),
        );

        if (! $verified) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        $user = $this->users->findByEmail($this->field($request, 'email'));

        return response()->json(['data' => $user !== null ? $this->userToArray($user) : null]);
    }

    public function verifyPasswordResetCode(VerifyPasswordResetCodeRequest $request): JsonResponse
    {
        $token = $this->resetPassword->verifyResetCode(
            $this->field($request, 'email'),
            $this->field($request, 'code'),
        );

        return response()->json(['data' => ['token' => $token]]);
    }

    public function confirmPendingEmail(int $id, string $hash): JsonResponse
    {
        if (! $this->emailVerification->confirmPendingEmail($id, $hash)) {
            return response()->json(['message' => 'Link de confirmação inválido ou expirado.'], 404);
        }

        return response()->json(['message' => 'Novo e-mail confirmado com sucesso.']);
    }

    private function field(FormRequest $request, string $key): string
    {
        /** @var string $value */
        $value = $request->validated($key);

        return $value;
    }

    private function nullableField(FormRequest $request, string $key): ?string
    {
        /** @var string|null $value */
        $value = $request->validated($key);

        return $value;
    }

    /**
     * @return array{data: array<string, mixed>, token: string}
     */
    private function sessionResponse(User $user): array
    {
        $model = UserModel::findOrFail($user->id);

        // The actual credential qor-website's SPA uses (ARCHITECTURE §2 —
        // cookie mode, never a client-held token): without this, every
        // subsequent cookie-based request (GET /profile, favorites, ...) is
        // unauthenticated in a real browser, even though login() itself
        // reports success — the `token` below is only for mobile bearer
        // clients.
        //
        // Sanctum's custom 'fan' guard (config/auth.php) is a stateless
        // RequestGuard — it has no ->login(). config/sanctum.php's 'guard'
        // list names the real session-backed guard(s) Sanctum actually
        // checks for a stateful (cookie) request, regardless of which named
        // guard a route declares — 'fan-session' here.
        Auth::guard('fan-session')->login($model);

        $token = $model->createToken('mobile')->plainTextToken;

        return ['data' => $this->userToArray($user), 'token' => $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture_url' => $user->profilePictureUrl,
            'email_verified' => $user->isVerified(),
        ];
    }
}
