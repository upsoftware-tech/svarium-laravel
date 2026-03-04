<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\Http\Middleware\AuthenticateMiddleware;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\OperationResult;
use Illuminate\Auth\Middleware\Authenticate as LaravelAuthenticateMiddleware;
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

        if (! $route) {
            abort(404);
        }

        $context = new PanelContext($panel, $request, $route['params']);
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
        $operation = app($operationClass);

        if (! empty($route['meta']['resource']) && method_exists($operation, 'setResource')) {
            $operation->setResource($route['meta']['resource']);
        }

        try {
            app(OperationAuthorizer::class)->authorize($operation, $context);
        } catch (AuthorizationException $e) {
            return response('Forbidden', 403);
        }

        $args = app(OperationParameterResolver::class)
            ->resolve($operation, $context);

        $panelMiddleware = $panel?->getMiddleware() ?? [];
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
            ->then(fn () => $operation->handle($context, ...$args));

        if ($result instanceof ComponentResult) {

            $panelObj = $panel;
            $layout = $operation::$layout ?: $panelObj?->layout;
            if (! $layout) {
                $panelObj = $panel instanceof \Upsoftware\Svarium\Panel\Panel
                    ? $panel
                    : app(PanelRegistry::class)->get($panel);
                $layout = $panelObj?->layout;
            }

            $result->setLayout($layout);
            $result->setView($operation::$view);
        }

        if ($result instanceof OperationResult) {

            return $result->toResponse();
        }

        return $result;
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
}
