<?php

namespace Upsoftware\Svarium\Resources\Components;

class Div extends Block
{
    public string $component = 'Div';
    public array|null $content = null;

    public function __construct(string|array|null $content)
    {
        parent::__construct();
        $this->content = $content;
    }

    public function content(array $content) {
        $this->content = $content;
        return $this;
    }

    public function props(): array {
        return $this->mergeOptions([
            'content' => $this->content ? $this->renderComponent($this->content) : null
        ]);
    }

    public function toArray(): array {
        return array_merge(parent::toArray(), ['props' => $this->props()]);
    }
}
