<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Security\RecordIdentifier;

class OperationRouter
{
    protected function resolveMiddleware(array $middleware, PanelContext $context): array
    {
        return array_map(function ($middleware) use ($context) {

            return function ($request, $next) use ($middleware, $context) {

                $instance = is_string($middleware)
                    ? app($middleware)
                    : $middleware;

                if (method_exists($instance, 'handle')) {
                    return $instance->handle($request, $next, $context);
                }

                return $next($request);
            };

        }, $middleware);
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

        $middleware = array_merge(
            config('svarium.middleware.web', []),
            $panel?->getMiddleware() ?? [],
            $operation::middleware()
        );

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
}
