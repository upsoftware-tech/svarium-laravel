<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Layouts\AuthLayout;
use Upsoftware\Svarium\Layouts\PanelLayout as DefaultPanelLayout;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\OperationRouter;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\Routing\Area;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\EmptyState;
use Upsoftware\Svarium\UI\Components\Flex;

class PanelRuntime
{
    public function handle(Request $request, Area $area): InertiaResponse|Response
    {
        try {

            return app(OperationRouter::class)->handle(
                $request,
                $area->name,
                $area->prefix
            );

        } catch (ValidationException $e) {

            // renderujemy ponownie tę samą operację
            $response = app(OperationRouter::class)
                ->handle($request->duplicate([], []), $area->name, $area->prefix);

            if ($response instanceof \Upsoftware\Svarium\Http\ComponentResult) {
                $response->withErrors($e->errors());
                return $response->toResponse();
            }

            throw $e;
        } catch (Throwable $throwable) {
            return $this->renderThrowable($request, $area, $throwable);
        }
    }

    protected function renderThrowable(Request $request, Area $area, Throwable $throwable): Response
    {
        $errorNumber = $this->resolveErrorNumber($throwable);
        $errorName = get_class($throwable);
        $errorMessage = trim((string) $throwable->getMessage());

        if ($errorMessage === '') {
            $errorMessage = __('Unknown error');
        }

        $statusCode = $this->resolveStatusCode($throwable);

        $component = Flex::make()
            ->children([
                EmptyState::make()
                    ->icon('lucide:triangle-alert')
                    ->iconColor('white')
                    ->icon()
                    ->title(__('Error'))
                    ->badge(__('Error number').': '.$errorNumber)
                    ->subtitle(__('Error name').': '.$errorName)
                    ->descriontion($errorMessage)
            ])
            ->items('center')
            ->justify('center')
            ->flex(1);

        $panel = $area->name !== null
            ? app(PanelRegistry::class)->get($area->name)
            : null;

        $result = new ComponentResult(
            $component,
            $this->resolveErrorLayoutClass($request, $area, $panel?->layout)
        );

        $result->setView('Svarium');

        $response = $result->toResponse();
        $response->setStatusCode($statusCode);

        return $response;
    }

    protected function resolveErrorLayoutClass(Request $request, Area $area, ?string $panelLayout): string
    {
        $fallbackLayout = $panelLayout ?? DefaultPanelLayout::class;

        if (! $this->isPublicAuthRequest($request)) {
            return $fallbackLayout;
        }

        $operationLayout = $this->resolveMatchedOperationLayout($request, $area);
        if ($operationLayout !== null) {
            return $operationLayout;
        }

        $configLayout = trim((string) config('upsoftware.auth.register.layout', ''));
        if ($configLayout !== '' && class_exists($configLayout)) {
            return $configLayout;
        }

        return class_exists(AuthLayout::class)
            ? AuthLayout::class
            : $fallbackLayout;
    }

    protected function resolveMatchedOperationLayout(Request $request, Area $area): ?string
    {
        $panelName = trim((string) $area->name);
        if ($panelName === '') {
            return null;
        }

        $path = trim($request->path(), '/');
        $prefix = trim((string) $area->prefix, '/');

        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = trim((string) substr($path, strlen($prefix)), '/');
        }

        $route = app(OperationRegistry::class)->resolve($panelName, $request->method(), $path);
        $operationClass = is_array($route) ? ($route['operation'] ?? null) : null;

        if (! is_string($operationClass) || $operationClass === '' || ! class_exists($operationClass)) {
            return null;
        }

        $layout = $operationClass::$layout ?? null;
        if (! is_string($layout) || trim($layout) === '' || ! class_exists($layout)) {
            return null;
        }

        return $layout;
    }

    protected function isPublicAuthRequest(Request $request): bool
    {
        $defaultRoutePatterns = [
            'panel.auth.login',
            'panel.auth.login.*',
            'panel.auth.reset',
            'panel.auth.reset.*',
            'panel.auth.register',
            'panel.auth.register.*',
            'panel.auth.method',
            'panel.auth.method.*',
            'panel.auth.verification',
            'panel.auth.verification.*',
            'panel.auth.redirect',
            'panel.auth.callback',
        ];

        $routePatterns = config('upsoftware.panel.public_auth_route_patterns', $defaultRoutePatterns);
        if (! is_array($routePatterns)) {
            $routePatterns = $defaultRoutePatterns;
        }

        foreach ($routePatterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->routeIs($pattern)) {
                return true;
            }
        }

        $panelPrefix = trim((string) config('upsoftware.panel.prefix', ''), '/');
        $base = $panelPrefix !== '' ? $panelPrefix.'/' : '';

        $defaultPathPatterns = [
            $base.'auth/login',
            $base.'auth/login/*',
            $base.'auth/reset',
            $base.'auth/reset/*',
            $base.'auth/register',
            $base.'auth/register/*',
        ];

        $pathPatterns = config('upsoftware.panel.public_auth_path_patterns', $defaultPathPatterns);
        if (! is_array($pathPatterns)) {
            $pathPatterns = $defaultPathPatterns;
        }

        foreach ($pathPatterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveErrorNumber(Throwable $throwable): string
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return (string) $throwable->getStatusCode();
        }

        $code = $throwable->getCode();

        if (is_int($code) && $code !== 0) {
            return (string) $code;
        }

        if (is_string($code) && trim($code) !== '') {
            return trim($code);
        }

        return '500';
    }

    protected function resolveStatusCode(Throwable $throwable): int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        $code = $throwable->getCode();
        if (is_int($code) && $code >= 400 && $code <= 599) {
            return $code;
        }

        return 500;
    }
}
