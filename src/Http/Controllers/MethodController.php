<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;
use Upsoftware\Svarium\Http\Requests\LoginMethodRequest;
use Upsoftware\Svarium\Services\Auth\AuthLoginService;

class MethodController extends Controller
{
    public function __construct(protected AuthLoginService $authLoginService)
    {
    }

    public function getAvailableMethods(mixed $user): array
    {
        if (! $this->authLoginService->isOtpGloballyEnabled()) {
            return [];
        }

        $definitions = [
            'app' => [
                'label' => __('svarium::messages.Google Authenticator App'),
                'description' => __('svarium::messages.The Google Authenticator app is available on all platforms, including iOS and Android'),
            ],
            'sms' => [
                'label' => __('svarium::messages.SMS message'),
                'description' => __('svarium::messages.SMS message to the registered phone number'),
            ],
            'email' => [
                'label' => __('svarium::messages.Email message'),
                'description' => __('svarium::messages.Email message to the registered email address'),
            ],
        ];

        $methods = [];

        $methodsToShow = $this->authLoginService->showAllOtpMethods()
            ? $this->authLoginService->supportedOtpMethods()
            : $this->authLoginService->allowedOtpMethods();

        foreach ($methodsToShow as $method) {
            if (! isset($definitions[$method])) {
                continue;
            }

            $isAllowed = $this->authLoginService->isOtpMethodAllowed($method);
            $isAvailable = $this->authLoginService->isOtpMethodAvailableForUser($user, $method);
            $isDisabled = ! $isAllowed || ! $isAvailable;

            if (! $this->authLoginService->showAllOtpMethods() && $isDisabled) {
                continue;
            }

            $methods[] = [
                'id' => $method,
                'disabled' => $isDisabled,
                'label' => $definitions[$method]['label'],
                'description' => $definitions[$method]['description'],
            ];
        }

        return $methods;
    }

    public function init($type, $userAuth)
    {
        $data = [];
        $userAuth = get_model('user_auth')::byHash($userAuth);
        $availableMethods = $this->getAvailableMethods($userAuth->user);
        $activeMethods = array_values(array_filter(
            $availableMethods,
            static fn (array $item): bool => ($item['disabled'] ?? true) === false
        ));

        if (! $this->authLoginService->showAllOtpMethods() && count($activeMethods) === 1) {
            $method = strtolower(trim((string) ($activeMethods[0]['id'] ?? '')));

            if ($method !== '') {
                $methodName = 'send' . ucfirst($method);
                if (method_exists($userAuth, $methodName)) {
                    $userAuth->{$methodName}($type);
                }

                return redirect()->to(route_panel('verification', ['type' => $type, 'userAuth' => $userAuth->hash]));
            }
        }

        $data['session'] = $userAuth->hash;
        $data['verificationMethods'] = $availableMethods;

        return inertia('Auth/Method', $data);
    }

    public function set(LoginMethodRequest $request, $type, $userAuth)
    {
        $userAuth = get_model('user_auth')::byHash($userAuth);
        $method = strtolower(trim((string) $request->method));
        $availableMethods = array_values(array_map(
            static fn (array $item): string => (string) ($item['id'] ?? ''),
            array_filter($this->getAvailableMethods($userAuth->user), static fn (array $item): bool => ($item['disabled'] ?? true) === false)
        ));

        if ($method === '' || ! in_array($method, $availableMethods, true)) {
            throw ValidationException::withMessages([
                'method' => [__('svarium::messages.Invalid verification method')],
            ]);
        }

        $methodName = 'send' . ucfirst($method);
        if (method_exists($userAuth, $methodName)) {
            try {
                $userAuth->{$methodName}($type);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'method' => [__('Selected verification method is not available.')],
                ]);
            }
        }

        return redirect()->to(route_panel('verification', ['type' => $type, 'userAuth' => $userAuth->hash]));
    }
}
