<?php

namespace Upsoftware\Svarium\Panel;

class Layout
{
    protected array $config = [];

    public function __construct(string $layoutClass)
    {
        $this->config['layout'] = $layoutClass;
    }

    public static function make(string $layoutClass): static
    {
        return new static($layoutClass);
    }

    /**
     * Set register schema (Component class/callable/array).
     */
    public function body(mixed $schema): static
    {
        $this->config['schema'] = $schema;

        return $this;
    }

    /**
     * Alias for body().
     */
    public function content(mixed $schema): static
    {
        return $this->body($schema);
    }

    public function enabled(bool $enabled = true): static
    {
        $this->config['enabled'] = $enabled;

        return $this;
    }

    public function layoutEnabled(bool $enabled = true): static
    {
        $this->config['layout_enabled'] = $enabled;

        return $this;
    }

    public function withoutLayout(bool $without = true): static
    {
        return $this->layoutEnabled(! $without);
    }

    public function skipMainLayout(bool $skip = true): static
    {
        $this->config['skip_main_layout'] = $skip;

        if ($skip) {
            $this->config['layout_enabled'] = false;
        }

        return $this;
    }

    public function config(array|Config $config): static
    {
        $items = $config instanceof Config ? $config->toArray() : $config;
        $this->config = array_replace_recursive($this->config, $items);

        return $this;
    }

    /**
     * Wrap rendered register tree with wrapper component(s).
     *
     * Supported values:
     * - string component name, e.g. "PanelLayout"
     * - FQCN of PHP UI component class
     * - UI Component instance
     * - node array (with "type")
     * - list of above
     */
    public function wrap(mixed $wrapper): static
    {
        if (! array_key_exists('wrap', $this->config)) {
            $this->config['wrap'] = $wrapper;
            return $this;
        }

        $current = $this->config['wrap'];

        if (is_array($current) && ! $this->isAssoc($current)) {
            $current[] = $wrapper;
            $this->config['wrap'] = $current;

            return $this;
        }

        $this->config['wrap'] = [$current, $wrapper];

        return $this;
    }

    public function wrapComponent(mixed $wrapper): static
    {
        return $this->wrap($wrapper);
    }

    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    public function toArray(): array
    {
        return $this->config;
    }
}
