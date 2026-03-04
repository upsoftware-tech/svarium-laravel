<?php

namespace Upsoftware\Svarium\Panel\Operations\Auth;

use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Layouts\AuthLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Text;

class MethodOperation extends Operation
{
    public static string|array $panels = '*';
    public static ?string $layout = AuthLayout::class;

    protected const ALLOWED_TYPES = ['login', 'register', 'reset'];

    public static function uri(): string
    {
        return 'auth/{type}/method/{userAuth}';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['app', 'sms', 'email'])],
        ];
    }

    public function schema(PanelContext $context): array
    {
        $userAuth = $this->resolveUserAuth($context);
        $availableMethods = array_values(array_filter(
            $this->getAvailableMethods($userAuth->user),
            static fn (array $item): bool => ($item['disabled'] ?? true) === false
        ));
        $options = array_map(
            static fn (array $item): array => [
                'value' => (string) ($item['id'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
            ],
            $availableMethods
        );

        return [
            Select::make('method')
                ->label(__('verification method'))
                ->placeholder(__('Select verification method'))
                ->options($options),
            Text::make(__('Selected verification method is not available.'))
                ->if($options === []),
            Button::make(__('Continue'))
                ->submit()
                ->if($options !== []),
        ];
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    public function defineTitle(): string
    {
        return __('Select verification method');
    }

    public function defineSubtitle(): string
    {
        return __('Select one of the following verification methods to proceed');
    }

    protected function layoutProps(PanelContext $context, ...$args): array
    {
        return [
            'title' => $this->defineTitle(),
            'subtitle' => $this->defineSubtitle(),
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $userAuth = $this->resolveUserAuth($context);
        $type = $this->resolveType($context);
        $method = strtolower(trim((string) ($context->validated()['method'] ?? '')));

        $availableMethods = array_values(array_map(
            static fn (array $item): string => (string) ($item['id'] ?? ''),
            array_filter(
                $this->getAvailableMethods($userAuth->user),
                static fn (array $item): bool => ($item['disabled'] ?? true) === false
            )
        ));

        if ($method === '' || ! in_array($method, $availableMethods, true)) {
            throw ValidationException::withMessages([
                'method' => [__('svarium::messages.Invalid verification method')],
            ]);
        }

        $methodName = 'send'.ucfirst($method);
        if (method_exists($userAuth, $methodName)) {
            try {
                $userAuth->{$methodName}($type);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'method' => [__('Selected verification method is not available.')],
                ]);
            }
        }

        return RedirectResult::to(route('panel.auth.verification', [
            'type' => $type,
            'userAuth' => $userAuth->hash,
        ]));
    }

    protected function resolveType(PanelContext $context): string
    {
        $type = strtolower(trim((string) ($context->params['type'] ?? '')));

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            abort(404);
        }

        return $type;
    }

    protected function resolveUserAuth(PanelContext $context): mixed
    {
        $hash = trim((string) ($context->params['userAuth'] ?? ''));
        $userAuth = $hash !== ''
            ? get_model('user_auth')::byHash($hash)
            : null;

        if (! $userAuth || ! $userAuth->user) {
            abort(404);
        }

        return $userAuth;
    }

    protected function getAvailableMethods(mixed $user): array
    {
        $authLoginService = app(\Upsoftware\Svarium\Services\Auth\AuthLoginService::class);

        if (! $authLoginService->isOtpGloballyEnabled()) {
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

        foreach ($authLoginService->allowedOtpMethods() as $method) {
            if (! isset($definitions[$method])) {
                continue;
            }

            $methods[] = [
                'id' => $method,
                'disabled' => ! $authLoginService->isOtpMethodAvailableForUser($user, $method),
                'label' => $definitions[$method]['label'],
                'description' => $definitions[$method]['description'],
            ];
        }

        return $methods;
    }
}
