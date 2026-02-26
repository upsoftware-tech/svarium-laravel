<?php

namespace Upsoftware\Svarium\Routing;

use Illuminate\Http\Request;
use Upsoftware\Svarium\Panel\PanelRegistry;

class AreaResolver
{
    public function resolve(Request $request): Area
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        if (($segments[0] ?? null) === 'api') {
            return new Area('api');
        }

        foreach (app(PanelRegistry::class)->all() as $panel) {

            if ($panel->prefix && $panel->prefix === ($segments[0] ?? null)) {
                return new Area('panel', $panel->name, $panel->prefix);
            }

            if ($panel->prefix === null) {
                return new Area('panel', $panel->name, null);
            }
        }

        return new Area('web');
    }
}
