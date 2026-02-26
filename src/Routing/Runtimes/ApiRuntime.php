<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\Routing\Area;

class ApiRuntime
{
    public function handle(Request $request, Area $area): Response
    {
        return response('API: ' . $request->path());
    }
}
