<?php

namespace Upsoftware\Svarium\Routing;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Panel\OperationParameterResolver;
use Upsoftware\Svarium\Registry\OperationRegistry;

class OperationRouter
{
    public function handle(
        Request $request,
        string $panelName,
        string $prefix
    ) {
        $path = trim($request->path(), '/');

        $operationClass = app(OperationRegistry::class)
            ->resolve($path);

        if (! $operationClass) {
            abort(404);
        }

        $operation = app($operationClass);

        $panel = app(\Upsoftware\Svarium\Panel\PanelRegistry::class)
            ->get($panelName);

        $context = new PanelContext($request, $panel, $prefix);

        $args = app(OperationParameterResolver::class)
            ->resolve($operation, $context);

        $result = $operation->handle(...$args);

        if (! $result instanceof OperationResult) {
            return $result;
        }

        if (! empty($result->getFlash())) {
            Inertia::share('flash', $result->getFlash());
        }

        return $result->toResponse();
    }
}
