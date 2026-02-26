<?php

namespace Upsoftware\Svarium\Bundles;

class Bundle
{
    /**
     * Lista modułów, które bundle aktywuje
     */
    public function modules(): array
    {
        return [];
    }

    /**
     * Opcjonalne middleware bundle
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Opcjonalne dodatkowe bootowanie
     */
    public function boot(): void
    {
    }
}
