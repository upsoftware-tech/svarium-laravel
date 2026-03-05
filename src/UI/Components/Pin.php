<?php

namespace Upsoftware\Svarium\UI\Components;

class Pin extends FieldComponent
{
    public function maxlength(int $length): static
    {
        return $this->prop('maxlength', max(1, $length));
    }

    public function pattern(string $pattern): static
    {
        return $this->prop('pattern', trim($pattern));
    }

    public function onlyDigits(): static
    {
        return $this->pattern('digits');
    }

    public function onlyChars(): static
    {
        return $this->pattern('chars');
    }

    public function onlyDigitsAndChars(): static
    {
        return $this->pattern('digits_and_chars');
    }
}
