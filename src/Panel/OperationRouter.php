<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Upsoftware\Svarium\Http\Middleware\AuthenticateMiddleware;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\OperationResult;
use Illuminate\Auth\Middleware\Authenticate as LaravelAuthenticateMiddleware;
use Upsoftware\Svarium\Layouts\PanelLayout as DefaultPanelLayout;
use Upsoftware\Svarium\Panel\Operations\DashboardOperation;
use Upsoftware\Svarium\Security\RecordIdentifier;

class OperationRouter
{
    protected function resolveMiddleware(array $middleware, PanelContext $context): array
    {
        $resolvedMiddleware = $this->expandMiddlewareAliases($middleware);

        return array_map(function ($middleware) use ($context) {

            if ($middleware instanceof \Closure) {
                return $middleware;
            }

            if (! is_string($middleware)) {
                return $middleware;
            }

            [$class, $parameters] = $this->parseMiddlewareString($middleware);

            if (! class_exists($class)) {
                return $middleware;
            }

            if (! $this->middlewareExpectsPanelContext($class)) {
                return $middleware;
            }

            return function ($request, $next) use ($class, $parameters, $context) {
                $instance = app($class);
                return $instance->handle($request, $next, $context, ...$parameters);
            };
        }, $resolvedMiddleware);
    }

    protected function expandMiddlewareAliases(array $middleware): array
    {
        $router = app(Router::class);
        $aliases = $router->getMiddleware();
        $groups = $router->getMiddlewareGroups();

        $resolved = [];

        foreach ($middleware as $definition) {
            $resolved = array_merge(
                $resolved,
                $this->expandMiddlewareDefinition($definition, $aliases, $groups)
            );
        }

        return $resolved;
    }

    protected function expandMiddlewareDefinition(
        mixed $middleware,
        array $aliases,
        array $groups
    ): array {
        if ($middleware instanceof \Closure || ! is_string($middleware)) {
            return [$middleware];
        }

        [$name, $parameters] = $this->parseMiddlewareString($middleware);

        if (isset($groups[$name])) {
            $expanded = [];

            foreach ($groups[$name] as $groupMiddleware) {
                $expanded = array_merge(
                    $expanded,
                    $this->expandMiddlewareDefinition($groupMiddleware, $aliases, $groups)
                );
            }

            return $expanded;
        }

        if (isset($aliases[$name])) {
            $resolved = $aliases[$name];

            if (is_string($resolved) && $parameters !== []) {
                return [$resolved.':'.implode(',', $parameters)];
            }

            return [$resolved];
        }

        return [$middleware];
    }

    protected function parseMiddlewareString(string $middleware): array
    {
        [$name, $parameterString] = array_pad(explode(':', $middleware, 2), 2, null);
        $parameters = $parameterString === null || $parameterString === ''
            ? []
            : array_values(array_filter(explode(',', $parameterString), fn (string $value) => $value !== ''));

        return [$name, $parameters];
    }

    protected function middlewareExpectsPanelContext(string $class): bool
    {
        if (! method_exists($class, 'handle')) {
            return false;
        }

        $parameters = (new ReflectionMethod($class, 'handle'))->getParameters();

        if (count($parameters) < 3) {
            return false;
        }

        $type = $parameters[2]->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === PanelContext::class;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if ($namedType instanceof ReflectionNamedType && $namedType->getName() === PanelContext::class) {
                    return true;
                }
            }
        }

        return false;
    }

    public function handle(Request $request, string $panel, ?string $prefix): Response
    {
        $panelName = $panel;
        $panel = app(PanelRegistry::class)->get($panelName);

        $path = trim($request->path(), '/');

        if ($prefix) {
            $path = trim(substr($path, strlen($prefix)), '/');
        }

        $route = app(OperationRegistry::class)
            ->resolve($panelName, $request->method(), $path);

        if (
            $this->shouldMountDashboardStartAtRoot($panel, $path, $route)
            && ($mountedRoute = $this->resolveDashboardStartOperationRoute($panel, $request->method())) !== null
        ) {
            $route = $mountedRoute;
        }

        if (! $route) {
            abort(404);
        }

        $context = new PanelContext($panel, $request, $route['params']);
        app()->instance(PanelContext::class, $context);
        $request->attributes->set('panel', $panelName);
        $context->input = new PanelInput($request->all());

        $bindings = app(BindingRegistry::class);

        foreach ($context->params as $key => $value) {

            if (is_string($value)) {
                try {
                    [, $decodedId] = RecordIdentifier::decode($value);
                    $value = $decodedId;
                } catch (\Throwable $e) {
                    // ignoruj
                }
            }

            $context->params[$key] = $bindings->resolve($key, $value);
        }

        $operationClass = $route['operation'];
        if ($operationClass === DashboardOperation::class && $request->isMethod('GET')) {
            if ($this->shouldMountDashboardStartAtRoot($panel, $path, $route)) {
                $mountedRoute = $this->resolveDashboardStartOperationRoute($panel, $request->method());
                if (is_array($mountedRoute) && isset($mountedRoute['operation'])) {
                    $route = $mountedRoute;
                    $context = new PanelContext($panel, $request, (array) ($route['params'] ?? []));
                    app()->instance(PanelContext::class, $context);
                    $request->attributes->set('panel', $panelName);
                    $context->input = new PanelInput($request->all());

                    $bindings = app(BindingRegistry::class);

                    foreach ($context->params as $key => $value) {
                        if (is_string($value)) {
                            try {
                                [, $decodedId] = RecordIdentifier::decode($value);
                                $value = $decodedId;
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }

                        $context->params[$key] = $bindings->resolve($key, $value);
                    }

                    $operationClass = (string) $route['operation'];
                }
            } elseif (($target = $this->resolveDashboardStartRedirectPath($panel)) !== null) {
                return redirect()->to($target);
            }
        }

        $operation = app($operationClass);

        if (! empty($route['meta']['resource']) && method_exists($operation, 'setResource')) {
            $operation->setResource($route['meta']['resource']);
        }

        $args = app(OperationParameterResolver::class)
            ->resolve($operation, $context);

        $panelMiddleware = $panel?->getMiddleware() ?? [];
        $panelMiddleware = $this->ensurePanelAuthMiddleware($panelMiddleware);
        if ($this->isPublicAuthRequest($request)) {
            $panelMiddleware = $this->withoutAuthMiddleware($panelMiddleware);
        }

        $middleware = array_merge(
            config('svarium.middleware.web', []),
            $panelMiddleware,
            $operation::middleware()
        );
        $middleware = $this->ensureMiddlewarePresent($middleware, LocaleMiddleware::class);

        $result = app(\Illuminate\Pipeline\Pipeline::class)
            ->send($request)
            ->through($this->resolveMiddleware($middleware, $context))
            ->then(function () use ($operation, $context, $args) {
                try {
                    app(OperationAuthorizer::class)->authorize($operation, $context);
                } catch (AuthorizationException $e) {
                    return response('Forbidden', 403);
                }

                try {
                    return $operation->handle($context, ...$args);
                } catch (NotFoundHttpException $e) {
                    if ($this->shouldRedirectAuthNotFound($context->request())) {
                        return redirect($this->panelRootPath($context->panel()))
                            ->with('alert_warning', [
                                'text' => 'Zaloguj się ponownie.',
                                'duration' => 0,
                            ]);
                    }

                    throw $e;
                }
            });

        if ($result instanceof ComponentResult) {

            $panelObj = $panel;
            $layout = $operation::$layout ?: $panelObj?->layout;
            if (! $layout) {
                $panelObj = $panel instanceof \Upsoftware\Svarium\Panel\Panel
                    ? $panel
                    : app(PanelRegistry::class)->get($panel);
                $layout = $panelObj?->layout;
            }

            $layout ??= DefaultPanelLayout::class;
            $result->setLayout($layout);
            $result->setView($operation::$view);
        }

        if ($result instanceof OperationResult) {

            return $result->toResponse();
        }

        return $result;
    }

    protected function shouldRedirectAuthNotFound(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');
        if ($path === '') {
            return false;
        }

        return preg_match('#(^|/)auth/(login|register|reset)/(verification|method)(/|$)#i', $path) === 1;
    }

    protected function panelRootPath(?Panel $panel): string
    {
        return svarium_panel_root_path($panel?->name);
    }

    protected function resolveDashboardStartRedirectPath(?Panel $panel): ?string
    {
        if (! $panel instanceof Panel) {
            return null;
        }

        $root = svarium_panel_root_path($panel->name);
        $start = svarium_panel_start_path($panel->name);

        if ($start === '' || $start === $root) {
            return null;
        }

        return $start;
    }

    protected function shouldMountDashboardStartAtRoot(?Panel $panel, string $path, ?array $route): bool
    {
        if (! $panel instanceof Panel) {
            return false;
        }

        if (! svarium_panel_start_at_root($panel->name)) {
            return false;
        }

        if (trim($path) !== '') {
            return false;
        }

        if ($route === null) {
            return true;
        }

        return (string) ($route['operation'] ?? '') === DashboardOperation::class;
    }

    protected function resolveDashboardStartOperationRoute(?Panel $panel, string $method = 'GET'): ?array
    {
        if (! $panel instanceof Panel) {
            return null;
        }

        $start = svarium_panel_start_path($panel->name);
        $root = svarium_panel_root_path($panel->name);

        if ($start === '' || $start === $root) {
            return null;
        }

        $startPath = (string) (parse_url($start, PHP_URL_PATH) ?? '');
        $startPath = trim($startPath, '/');

        $panelPrefix = trim((string) $panel->prefix, '/');
        if ($panelPrefix !== '' && str_starts_with($startPath, $panelPrefix.'/')) {
            $startPath = trim(substr($startPath, strlen($panelPrefix) + 1), '/');
        } elseif ($panelPrefix !== '' && $startPath === $panelPrefix) {
            $startPath = '';
        }

        if ($startPath === '') {
            return null;
        }

        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === 'HEAD') {
            $normalizedMethod = 'GET';
        }

        $route = app(OperationRegistry::class)->resolve($panel->name, $normalizedMethod, $startPath);
        if (! is_array($route)) {
            return null;
        }

        if ((string) ($route['operation'] ?? '') === DashboardOperation::class) {
            return null;
        }

        return $route;
    }

    protected function isPublicAuthRequest(Request $request): bool
    {
        return svarium_is_public_auth_request($request);
    }

    protected function withoutAuthMiddleware(array $middleware): array
    {
        $filtered = [];

        foreach ($middleware as $definition) {
            if (! is_string($definition)) {
                $filtered[] = $definition;
                continue;
            }

            $normalized = trim($definition);
            if ($normalized === '') {
                continue;
            }

            if (
                $normalized === 'auth'
                || Str::startsWith($normalized, 'auth:')
                || $normalized === 'auth.panel'
                || Str::startsWith($normalized, 'auth.panel:')
                || $normalized === LaravelAuthenticateMiddleware::class
                || $normalized === AuthenticateMiddleware::class
            ) {
                continue;
            }

            $filtered[] = $definition;
        }

        return $filtered;
    }

    protected function ensureMiddlewarePresent(array $middleware, string $middlewareClass): array
    {
        foreach ($middleware as $definition) {
            if ($definition === $middlewareClass) {
                return $middleware;
            }
        }

        array_unshift($middleware, $middlewareClass);

        return $middleware;
    }

    protected function ensurePanelAuthMiddleware(array $middleware): array
    {
        foreach ($middleware as $definition) {
            if (! is_string($definition)) {
                continue;
            }

            $normalized = trim($definition);
            if ($normalized === '') {
                continue;
            }

            if (
                $normalized === 'auth'
                || Str::startsWith($normalized, 'auth:')
                || $normalized === 'auth.panel'
                || Str::startsWith($normalized, 'auth.panel:')
                || $normalized === LaravelAuthenticateMiddleware::class
                || $normalized === AuthenticateMiddleware::class
            ) {
                return $middleware;
            }
        }

        array_unshift($middleware, 'auth.panel');

        return $middleware;
    }
}
