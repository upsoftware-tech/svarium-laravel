<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasHref;

class Link extends Component
{
    use HasHref;

    public static function make(?string $name = null): static
    {
        $instance = parent::make($name);
        $instance->prop('tag', 'Link');
        if (is_string($name) && trim($name) !== '') {
            $instance->text($name);
        }

        return $instance;
    }

    public function text(string $text): static
    {
        return $this->prop('text', $text);
    }

    public function route(string $name, array $params = []): static
    {
        $this->prop('route', $name);

        if ($params !== []) {
            $this->params($params);
        }

        return $this;
    }

    public function panelRoute(string $name, array $params = []): static
    {
        return $this->route(panel_route_name($name), $params);
    }

    public function panelHref(string $path = '', ?string $panel = null): static
    {
        return $this->href(panel_href($path, $panel));
    }

    public function params(array $params): static
    {
        return $this->prop('params', $params);
    }

    public function newWindow(bool $enabled = true): static
    {
        return $this->prop('newWindow', $enabled);
    }

    public function tag(string $tag): static
    {
        $normalized = strtolower(trim($tag));

        if ($normalized === 'a') {
            return $this->prop('tag', 'a');
        }

        return $this->prop('tag', 'Link');
    }
}
