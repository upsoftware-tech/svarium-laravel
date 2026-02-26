<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\Panel\OperationRouter;
use Upsoftware\Svarium\Routing\Area;

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
        }
    }
}
