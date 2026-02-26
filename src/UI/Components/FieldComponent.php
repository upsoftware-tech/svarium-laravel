<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\HasValidation;

abstract class FieldComponent extends Component
{
    use HasValidation;

    protected ?string $name = null;
    protected ?string $label = '';
    protected ?string $value = null;

    public function __construct(?string $name = null)
    {
        $this->name = $name;

        if ($name) {
            $this->props['name'] = $name;
        }
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function __call(string $method, array $arguments)
    {
        if (str_contains($method, '_')) {

            $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $method))));

            if (method_exists($this, $camel)) {
                return $this->{$camel}(...$arguments);
            }
        }

        throw new \BadMethodCallException(
            "Method {$method} does not exist on ".static::class
        );
    }

    public function value(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        $this->props['label'] = $label;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? '';
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->value !== null) {
            $array['props']['value'] = $this->value;
        }

        return $array;
    }
}
