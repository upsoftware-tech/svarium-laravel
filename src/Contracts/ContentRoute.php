<?php

namespace Upsoftware\Svarium\Contracts;

use Illuminate\Http\Request;
use Upsoftware\Svarium\Routing\ContentMatch;

interface ContentRoute
{
    public function match(Request $request): ?ContentMatch;
}
