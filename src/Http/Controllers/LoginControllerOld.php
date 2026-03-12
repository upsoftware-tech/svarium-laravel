<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class LoginController extends Controller
{
    protected AuthLoginService $authLoginService;

    public function __construct(?AuthLoginService $authLoginService = null)
    {
        $this->authLoginService = $authLoginService ?? app(AuthLoginService::class);
    }

    public function init(Request $request) {
        if (Auth::check()) {
            return redirect('/');
        }

        $data = $this->safe(
            fn () => get_model('setting')::getSettingGlobal('login.config', []),
            []
        );

        if (! is_array($data)) {
            $data = [];
        }

        return show('Auth/Login', $data);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);

        try {
            $result = $this->authLoginService->attempt(
                $request,
                (string) $request->email,
                (string) $request->password
            );

            if (($result['status'] ?? null) === AuthLoginService::STATUS_INVALID) {
                throw ValidationException::withMessages([
                    'email' => [__('Invalid email address or password')],
                ]);
            }

            if (($result['status'] ?? null) === AuthLoginService::STATUS_OTP_REQUIRED && ! empty($result['otp_url'])) {
                return redirect()->to((string) $result['otp_url']);
            }

            if (($result['status'] ?? null) === AuthLoginService::STATUS_AUTHENTICATED && ! empty($result['redirect_url'])) {
                return redirect()->to((string) $result['redirect_url']);
            }

            throw ValidationException::withMessages([
                'email' => [__('Invalid email address or password')],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid email address or password')],
            ]);
        }
    }

    public function loginUser(Request $request, mixed $user): RedirectResponse
    {
        $redirectUrl = $this->authLoginService->loginUser($request, $user);

        return redirect()->to($redirectUrl);
    }

    protected function safe(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
