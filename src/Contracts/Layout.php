<?php

namespace Upsoftware\Svarium\Contracts;

use Upsoftware\Svarium\Resources\Components\Component;

interface Layout
{
    public function default(): array|Component;
    public function content(): array|Component;
    public function getContent(): array|Component|null;
}
