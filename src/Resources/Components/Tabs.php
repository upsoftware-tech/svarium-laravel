<?php

namespace Upsoftware\Svarium\Resources\Components;

class Tabs extends Component
{
    public string $component = 'tabs';
    public ?array $tabs = [];

    public function __construct(?array $tabs = []) {
        $this->is_multidimensional($tabs) ? $this->tabs($tabs) : $this->tab($tabs);
    }

    public function tabs(array $tabs): static {
        if (!$this->is_multidimensional($tabs)) {
            $this->tab($tabs);
            return $this;
        }
        foreach($tabs as $tab) {
            //$this->tab($tab);
        }
        return $this;
    }

    public function tab(array $tab): static {
        if ($this->is_multidimensional($tab)) {
            $this->tabs($tab);
            return $this;
        }
        if (isset($tab["content"])) {
            $tab["content"] = $this->renderComponent($tab["content"]);
        }
        $this->tabs[] = $tab;
        return $this;
    }

    public function props(): array
    {
        return $this->mergeOptions(array_filter([
            'tabs'  => $this->tabs,
        ], fn($val) => !empty($val)));
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'props' => $this->props()];
    }
}
