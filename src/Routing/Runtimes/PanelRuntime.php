<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Upsoftware\Svarium\Http\ComponentResult;
use Upsoftware\Svarium\Layouts\PanelLayout as DefaultPanelLayout;
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
            return $this->renderThrowable($area, $throwable);
        }
    }

    protected function renderThrowable(Area $area, Throwable $throwable): Response
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
            $panel?->layout ?? DefaultPanelLayout::class
        );

        $result->setView('Svarium');

        $response = $result->toResponse();
        $response->setStatusCode($statusCode);

        return $response;
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
