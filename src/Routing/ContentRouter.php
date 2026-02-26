<?php

namespace Upsoftware\Svarium\Routing;

use Illuminate\Http\Request;

class ContentRouter
{
    public function handle(Request $request)
    {
        $methodNotAllowed = false;

        foreach (app(ContentRouteRegistry::class)->all() as $route) {

            $match = app($route)->match($request);

            if (!$match) {
                continue;
            }

            if (!$match->methodAllowed($request)) {
                $methodNotAllowed = true;
                continue;
            }

            return app(ContentRenderer::class)->render($match);
        }

        abort($methodNotAllowed ? 405 : 404);
    }
}
