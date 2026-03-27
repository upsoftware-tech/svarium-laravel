<?php

namespace Upsoftware\Svarium\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Upsoftware\Svarium\Auth\AuthManager;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class ApiAuthUserController extends ApiAuthLoginController
{
    public function __invoke(
        Request $request,
        AuthLoginService $authLoginService,
        AuthManager $authManager
    ): JsonResponse
    {
        $user = $request->user();

        if (! is_object($user)) {
            return response()->json([
                'status' => 'invalid',
                'message' => __('Unauthenticated.'),
                'errors' => [
                    'token' => [__('Unauthenticated.')],
                ],
            ], 401);
        }

        $token = trim((string) $request->bearerToken());

        return response()->json([
            'status' => 'authenticated',
            'token' => $token !== '' ? $token : null,
            'requires_otp' => false,
            'user' => $this->serializeUser($user),
        ], 200);
    }
}
