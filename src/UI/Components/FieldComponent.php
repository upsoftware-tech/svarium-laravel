<?php

namespace Upsoftware\Svarium\UI\Components;

use ReflectionMethod;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\HasValidation;

abstract class FieldComponent extends Component
{
    use HasValidation;

    protected ?string $name = null;
    protected ?string $label = '';
    protected mixed $value = null;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
        $this->props['language'] = false;

        if ($name) {
            $this->props['name'] = $name;
            $this->applyRegisteredAttributes($name);
        }
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (str_contains($method, '_')) {

            $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $method))));

            if (method_exists($this, $camel)) {
                return $this->{$camel}(...$arguments);
            }
        }

        return parent::__call($method, $arguments);
    }

    public function value(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->value($value);

        return $this->apiDefault($value);
    }

    public function apiDefault(mixed $value): static
    {
        return $this->prop('apiDefault', $value);
    }

    public function example(mixed $value): static
    {
        return $this->apiExample($value);
    }

    public function apiExample(mixed $value): static
    {
        return $this->prop('apiExample', $value);
    }

    public function apiFormat(string $format): static
    {
        $normalized = trim($format);

        return $this->prop('apiFormat', $normalized);
    }

    public function schemaFormat(string $format): static
    {
        return $this->apiFormat($format);
    }

    public function apiMinimum(int|float $value): static
    {
        return $this->prop('apiMinimum', $value);
    }

    public function apiMaximum(int|float $value): static
    {
        return $this->prop('apiMaximum', $value);
    }

    public function apiMinLength(int $value): static
    {
        return $this->prop('apiMinLength', max(0, $value));
    }

    public function apiMaxLength(int $value): static
    {
        return $this->prop('apiMaxLength', max(0, $value));
    }

    public function apiMinItems(int $value): static
    {
        return $this->prop('apiMinItems', max(0, $value));
    }

    public function apiMaxItems(int $value): static
    {
        return $this->prop('apiMaxItems', max(0, $value));
    }

    public function apiPattern(string $pattern): static
    {
        return $this->prop('apiPattern', trim($pattern));
    }

    public function apiEnum(array $values): static
    {
        return $this->prop('apiEnum', array_values($values));
    }

    public function apiOptions(array $options): static
    {
        return $this->prop('apiOptions', $options);
    }

    public function options(array $options): static
    {
        return $this->apiOptions($options);
    }

    public function possibleOptions(array $options): static
    {
        return $this->apiOptions($options);
    }

    public function description(string $description): static
    {
        return $this->prop('description', $description);
    }

    public function label(string $label): static
    {
        $this->label = $label;
        $this->props['label'] = $label;
        return $this;
    }

    public function hint(string $hint): static
    {
        return $this->prop('hint', $hint);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function autocomplete(string $autocomplete): static
    {
        return $this->prop('autocomplete', $autocomplete);
    }

    public function step(int|float|string $step): static
    {
        return $this->prop('step', $step);
    }

    public function language(bool $enabled = true): static
    {
        return $this->prop('language', $enabled);
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
        $array['props']['required'] = $this->hasRequiredRule();

        if ($this->value !== null) {
            $array['props']['value'] = $this->value;
        }

        return $array;
    }

    protected function applyRegisteredAttributes(string $name): void
    {
        $attributes = app(FieldAttributesRegistry::class)->input($name);

        foreach ($attributes as $attribute => $value) {
            $this->applyRegisteredAttribute((string) $attribute, $value);
        }
    }

    protected function applyRegisteredAttribute(string $attribute, mixed $value): void
    {
        $attribute = trim($attribute);

        if ($attribute === '' || $attribute === 'name') {
            return;
        }

        $method = $attribute;

        if (! method_exists($this, $method) && str_contains($method, '_')) {
            $method = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $method))));
        }

        if (! method_exists($this, $method)) {
            $this->prop($attribute, $value);
            return;
        }

        $reflection = new ReflectionMethod($this, $method);
        $required = $reflection->getNumberOfRequiredParameters();

        if (is_bool($value) && $required === 0) {
            if ($value) {
                $this->{$method}();
            }

            return;
        }

        if (is_array($value) && ! $this->isAssoc($value)) {
            $parameters = $reflection->getParameters();
            $isVariadic = isset($parameters[0]) && $parameters[0]->isVariadic();
            $requiredCount = $reflection->getNumberOfRequiredParameters();

            if ($isVariadic || $requiredCount > 1) {
                $this->{$method}(...$value);
            } else {
                $this->{$method}($value);
            }

            return;
        }

        if ($required === 0 && $value === null) {
            $this->{$method}();
            return;
        }

        $this->{$method}($value);
    }

    protected function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
