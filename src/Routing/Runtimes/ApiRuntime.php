<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Upsoftware\Svarium\Http\OperationResult;
use Upsoftware\Svarium\Panel\BindingRegistry;
use Upsoftware\Svarium\Panel\OperationParameterResolver;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Routing\Area;
use Upsoftware\Svarium\Security\RecordIdentifier;

class ApiRuntime
{
    public function handle(Request $request, Area $area): Response
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $route = app(OperationRegistry::class)->resolve('__api', $method, $path);
        if (! is_array($route)) {
            return new JsonResponse(['message' => 'Not Found'], 404);
        }

        $operationClass = (string) ($route['operation'] ?? '');
        if ($operationClass === '' || ! class_exists($operationClass)) {
            return new JsonResponse(['message' => 'Not Found'], 404);
        }

        try {
            $panel = Panel::make('api')->noPrefix();
            $context = new PanelContext($panel, $request, (array) ($route['params'] ?? []));
            app()->instance(PanelContext::class, $context);

            $bindings = app(BindingRegistry::class);
            foreach ($context->params as $key => $value) {
                if (is_string($value)) {
                    try {
                        [, $decodedId] = RecordIdentifier::decode($value);
                        $value = $decodedId;
                    } catch (Throwable) {
                        // Ignore and use raw value.
                    }
                }

                $context->params[$key] = $bindings->resolve((string) $key, $value);
            }

            $operation = app($operationClass);

            if (! empty($route['meta']['resource']) && method_exists($operation, 'setResource')) {
                $operation->setResource($route['meta']['resource']);
            }

            $args = app(OperationParameterResolver::class)->resolve($operation, $context);

            if (method_exists($operation, 'apiRun')) {
                $apiResult = $operation->apiRun($context, ...$args);
                if ($apiResult !== null) {
                    return $this->toResponse($apiResult);
                }
            }

            return $this->toResponse($operation->handle($context, ...$args));
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (HttpExceptionInterface $e) {
            $status = max(400, min(599, $e->getStatusCode()));

            return new JsonResponse([
                'message' => trim((string) $e->getMessage()) !== ''
                    ? trim((string) $e->getMessage())
                    : Response::$statusTexts[$status] ?? 'Error',
            ], $status);
        } catch (Throwable $e) {
            return new JsonResponse([
                'message' => trim((string) $e->getMessage()) !== ''
                    ? trim((string) $e->getMessage())
                    : 'Server Error',
            ], 500);
        }
    }

    protected function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof OperationResult) {
            return $result->toResponse();
        }

        if (is_array($result)) {
            return new JsonResponse($result, 200);
        }

        if (is_scalar($result) || $result === null) {
            return new JsonResponse(['data' => $result], 200);
        }

        return new JsonResponse([
            'message' => 'Unsupported API response type.',
        ], 500);
    }
}
