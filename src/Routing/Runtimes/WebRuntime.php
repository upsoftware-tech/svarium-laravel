<?php

namespace Upsoftware\Svarium\Routing\Runtimes;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebRuntime
{
    public function handle(Request $request): Response
    {
        return response('WEB: ' . $request->path());
    }
}
