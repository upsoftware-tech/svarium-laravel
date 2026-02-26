<?php

namespace Upsoftware\Svarium\UI\Contracts;

use Upsoftware\Svarium\UI\Component;

interface LayoutSection
{
    public function build(): Component|array|null;
}
