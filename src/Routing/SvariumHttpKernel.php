<?php

namespace Upsoftware\Svarium\Routing;

use Illuminate\Http\Request;
use Upsoftware\Svarium\Routing\Runtimes\ApiRuntime;
use Upsoftware\Svarium\Routing\Runtimes\PanelRuntime;
use Upsoftware\Svarium\Routing\Runtimes\WebRuntime;

class SvariumHttpKernel
{
    public function __invoke(Request $request)
    {
        $area = app(AreaResolver::class)->resolve($request);

        return match ($area->type) {
            'panel' => app(PanelRuntime::class)->handle($request, $area),
            'api'   => app(ApiRuntime::class)->handle($request, $area),
            'web'   => app(WebRuntime::class)->handle($request, $area),
        };
    }
}
