<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class ApiAuthOtpSendController extends Controller
{
    protected const ALLOWED_TYPES = ['login', 'register', 'reset'];

    public function __invoke(
        Request $request,
        AuthLoginService $authLoginService,
        string $userAuth
    ): JsonResponse {
        $data = $this->validated($request);
        $authType = strtolower(trim((string) ($data['type'] ?? '')));
        $method = strtolower(trim((string) ($data['method'] ?? '')));

        $userAuthModel = $this->resolveUserAuth($userAuth, $authType);
        $user = $userAuthModel->user ?? null;

        if (! is_object($user)) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid OTP session token.')],
            ]);
        }

        if (
            ! $authLoginService->isOtpGloballyEnabled()
            || ! $authLoginService->isOtpMethodAllowed($method)
            || ! $authLoginService->isOtpMethodAvailableForUser($user, $method)
        ) {
            throw ValidationException::withMessages([
                'method' => [__('svarium::messages.Invalid verification method')],
            ]);
        }

        if ($this->hasActiveVerificationCodeForMethod($userAuthModel, $method)) {
            return response()->json([
                'status' => 'otp_code_active',
                'requires_otp' => true,
                'otp_token' => $userAuthModel->hash,
                'method' => $method,
                'message' => __('Verification code is already active.'),
            ], 200);
        }

        $cooldownSeconds = $this->resendCooldownSecondsFromLastCode($userAuthModel, $method);
        if ($cooldownSeconds > 0) {
            return response()->json([
                'status' => 'otp_rate_limited',
                'requires_otp' => true,
                'otp_token' => $userAuthModel->hash,
                'method' => $method,
                'retry_after' => $cooldownSeconds,
                'message' => __('Too many resend requests. Try again in :seconds seconds.', ['seconds' => $cooldownSeconds]),
            ], 429);
        }

        $methodName = 'send'.ucfirst($method);
        if (! method_exists($userAuthModel, $methodName)) {
            throw ValidationException::withMessages([
                'method' => [__('Selected verification method is not available.')],
            ]);
        }

        try {
            if ($method === 'email') {
                $userAuthModel->{$methodName}($authType);
            } else {
                $userAuthModel->{$methodName}();
            }
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'method' => [__('Selected verification method is not available.')],
            ]);
        }

        return response()->json([
            'status' => 'otp_code_sent',
            'requires_otp' => true,
            'otp_token' => $userAuthModel->hash,
            'method' => $method,
            'message' => __('A new verification code has been sent.'),
        ], 200);
    }

    /**
     * @return array{type:string,method:string}
     */
    protected function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', Rule::in(self::ALLOWED_TYPES)],
            'method' => ['required', 'string', Rule::in(['app', 'sms', 'email'])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{type:string,method:string} $data */
        $data = $validator->validated();

        return $data;
    }

    protected function resolveUserAuth(string $hash, string $type): mixed
    {
        $normalizedHash = trim($hash);

        $userAuth = $normalizedHash !== ''
            ? get_model('user_auth')::byHash($normalizedHash)
            : null;

        if (! $userAuth || $this->isUserAuthTokenExpired($userAuth)) {
            throw ValidationException::withMessages([
                'token' => [__('Invalid OTP session token.')],
            ]);
        }

        $storedType = strtolower(trim((string) ($userAuth->type ?? '')));
        if ($storedType === '' || $storedType !== $type) {
            throw ValidationException::withMessages([
                'type' => [__('Invalid authentication type for OTP token.')],
            ]);
        }

        return $userAuth;
    }

    protected function isUserAuthTokenExpired(mixed $userAuth): bool
    {
        $ttlMinutes = max(1, (int) config('upsoftware.auth.otp.token_ttl_minutes', 10));
        $createdAtValue = $userAuth->created_at ?? null;

        if (! $createdAtValue) {
            return true;
        }

        try {
            $createdAt = Carbon::parse((string) $createdAtValue);
        } catch (Throwable) {
            return true;
        }

        return now()->greaterThan($createdAt->copy()->addMinutes($ttlMinutes));
    }

    protected function hasActiveVerificationCodeForMethod(mixed $userAuth, string $method): bool
    {
        $normalizedMethod = strtolower(trim($method));

        if ($normalizedMethod === '') {
            return false;
        }

        return $userAuth->code()
            ->where('method', $normalizedMethod)
            ->where(function ($query) {
                $query->whereNull('is_used')
                    ->orWhere('is_used', false);
            })
            ->where('expired_at', '>=', now())
            ->exists();
    }

    protected function resendCooldownSecondsFromLastCode(mixed $userAuth, string $method): int
    {
        $cooldown = max(0, (int) config('upsoftware.auth.otp.resend_seconds', 60));
        if ($cooldown <= 0) {
            return 0;
        }

        $latestCreatedAt = $userAuth->code()
            ->where('method', strtolower(trim($method)))
            ->latest('id')
            ->value('created_at');

        if (! $latestCreatedAt) {
            return 0;
        }

        try {
            $createdAt = Carbon::parse((string) $latestCreatedAt);
        } catch (Throwable) {
            return 0;
        }

        $availableAt = $createdAt->copy()->addSeconds($cooldown);
        if (now()->greaterThanOrEqualTo($availableAt)) {
            return 0;
        }

        return max(1, (int) now()->diffInSeconds($availableAt));
    }
}

