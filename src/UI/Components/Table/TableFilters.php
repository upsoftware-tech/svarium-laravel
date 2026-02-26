<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Component;

class TableFilters extends Component
{
    public function filters(array $filters): static
    {
        return $this->prop('filters', $filters);
    }
}
