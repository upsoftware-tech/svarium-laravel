<?php

namespace Upsoftware\Svarium\Routing;

class Area
{
    public function __construct(
        public string $type,
        public ?string $name = null,
        public ?string $prefix = null,
    ) {}
}
