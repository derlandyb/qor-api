<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use QOR\App\Http\Controllers\Controller;

/**
 * Local/testing-only — this route is never registered outside those two
 * environments (see routes/api_v1.php), so it doesn't exist at all in
 * production. Lets qor-website's E2E suite retrieve the OTP code just
 * issued for a purpose+email, since OtpAdapter only ever persists a
 * one-way hash of it durably (config('qor.auth.otp_length')-digit code,
 * see that class).
 */
class DebugController extends Controller
{
    public function lastOtpCode(Request $request): JsonResponse
    {
        $purpose = (string) $request->query('purpose', '');
        $email = (string) $request->query('email', '');

        $code = Cache::get("otp_debug:{$purpose}:{$email}");

        if ($code === null) {
            return response()->json(['message' => 'Nenhum código encontrado.'], 404);
        }

        return response()->json(['data' => ['code' => $code]]);
    }
}
