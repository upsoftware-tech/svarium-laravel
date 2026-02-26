<?php

namespace Upsoftware\Svarium\Panel;

class PanelRegistry
{
    protected array $panels = [];

    public function register(Panel $panel): void
    {
        $this->panels[$panel->name] = $panel;
    }

    public function get(string $name): ?Panel
    {
        return $this->panels[$name] ?? null;
    }

    public function all(): array
    {
        return $this->panels;
    }
}
