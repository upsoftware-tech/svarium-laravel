<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TabItem extends Component
{
    use HasChildren;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static($name);

        if (is_string($name)) {
            $instance->prop('name', $name);
        }

        return $instance;
    }

    public function disabled(bool $state = true): static
    {
        return $this->prop('disabled', $state);
    }

    public function icon(string $icon): static
    {
        return $this->prop('icon', $icon);
    }

    public function badge(string|int $value): static
    {
        return $this->prop('badge', $value);
    }

    public function url(string $url): static
    {
        $value = trim($url);

        if ($value === '') {
            return $this;
        }

        return $this->prop('url', $value);
    }

    public function newWindow(bool $state = true): static
    {
        return $this->prop('newWindow', $state);
    }

    public function event(string|array $event, mixed $payload = null): static
    {
        if (is_array($event)) {
            return $this->prop('event', $event);
        }

        $name = trim($event);
        if ($name === '') {
            return $this;
        }

        return $this->prop('event', [
            'name' => $name,
            'payload' => $payload,
        ]);
    }

    public function eventTarget(string $target): static
    {
        $normalized = strtolower(trim($target));

        if (! in_array($normalized, ['window', 'document'], true)) {
            $normalized = 'window';
        }

        return $this->prop('eventTarget', $normalized);
    }
}
