<?php

namespace Upsoftware\Svarium\Routing;

use Illuminate\Http\Request;

class ContentMatch
{
    public function __construct(
        public string $type,
        public mixed $record,
        public string $view,
        public array $data = [],
        public array $allowedMethods = ['GET']
    ) {}

    public function methodAllowed(Request $request): bool
    {
        return in_array($request->method(), $this->allowedMethods);
    }
}
