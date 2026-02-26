<?php

namespace Upsoftware\Svarium\UI\Concerns;

trait HasValidation
{
    protected array $rules = [];
    protected array $messages = [];
    protected ?string $attribute = null;

    public function rules(string|array $rules): static
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            $this->rules[] = $rule;
        }

        return $this;
    }

    public function attribute(string $name): static
    {
        $this->attribute = $name;
        return $this;
    }

    public function getValidationAttribute(): ?string
    {
        return $this->attribute;
    }

    public function messages(array $messages): static
    {
        foreach ($messages as $rule => $message) {
            $this->messages[$rule] = $message;
        }

        return $this;
    }

    public function message(string $rule, string $text): static
    {
        $this->messages[$rule] = $text;
        return $this;
    }

    public function rule(string $rule): static
    {
        $this->rules[] = $rule;
        return $this;
    }

    public function nullable(): static
    {
        return $this->rule('nullable');
    }

    /* REQUIRED */
    public function required(): static
    {
        return $this->rule('required');
    }

    public function messageRequired(string $text): static
    {
        return $this->message('required', $text);
    }

    /* ACCEPTED */
    public function accepted(): static
    {
        return $this->rule('accepted');
    }

    public function messageAccepted(string $text): static
    {
        return $this->message('accepted', $text);
    }

    /* ACCEPTED_IF */
    public function acceptedIf(string $anotherField, string|int|bool $value): static
    {
        return $this->rule("accepted_if:$anotherField,$value");
    }

    public function messageAcceptedIf(string $text): static
    {
        return $this->message('accepted_if', $text);
    }

    /* ACTIVE_URL */
    public function activeUrl(): static
    {
        return $this->rule('active_url');
    }

    public function messageActiveUrl(string $text): static
    {
        return $this->message('active_url', $text);
    }

    /* AFTER */
    public function after(string $dateOrField): static
    {
        return $this->rule("after:$dateOrField");
    }

    public function messageAfter(string $text): static
    {
        return $this->message('after', $text);
    }

    /* AFTER_OR_EQUAL */
    public function afterOrEqual(string $dateOrField): static
    {
        return $this->rule("after_or_equal:$dateOrField");
    }

    public function messageAfterOrEqual(string $text): static
    {
        return $this->message('after_or_equal', $text);
    }

    /* ANY_OF */
    public function anyOf(string|array $rules): static
    {
        if (is_array($rules)) {
            $rules = implode(',', $rules);
        }

        return $this->rule("any_of:$rules");
    }

    public function messageAnyOf(string $text): static
    {
        return $this->message('any_of', $text);
    }

    /* ALPHA */
    public function alpha(): static
    {
        return $this->rule('alpha');
    }

    public function messageAlpha(string $text): static
    {
        return $this->message('alpha', $text);
    }

    /* ALPHA_DASH */
    public function alphaDash(): static
    {
        return $this->rule('alpha_dash');
    }

    public function messageAlphaDash(string $text): static
    {
        return $this->message('alpha_dash', $text);
    }

    /* ALPHA_NUM */
    public function alphaNum(): static
    {
        return $this->rule('alpha_num');
    }

    public function messageAlphaNum(string $text): static
    {
        return $this->message('alpha_num', $text);
    }

    /* ARRAY */
    public function array(array|string|null $keys = null): static
    {
        if ($keys === null) {
            return $this->rule('array');
        }

        if (is_array($keys)) {
            $keys = implode(',', $keys);
        }

        return $this->rule("array:$keys");
    }

    public function messageArray(string $text): static
    {
        return $this->message('array', $text);
    }

    /* ASCII */
    public function ascii(): static
    {
        return $this->rule('ascii');
    }

    public function messageAscii(string $text): static
    {
        return $this->message('ascii', $text);
    }

    /* BAIL */
    public function bail(): static
    {
        return $this->rule('bail');
    }

    /* BEFORE */
    public function before(string $dateOrField): static
    {
        return $this->rule("before:$dateOrField");
    }

    public function messageBefore(string $text): static
    {
        return $this->message('before', $text);
    }

    /* BEFORE_OR_EQUAL */
    public function beforeOrEqual(string $dateOrField): static
    {
        return $this->rule("before_or_equal:$dateOrField");
    }

    public function messageBeforeOrEqual(string $text): static
    {
        return $this->message('before_or_equal', $text);
    }

    /* BETWEEN */
    public function between(int|float $min, int|float $max): static
    {
        return $this->rule("between:$min,$max");
    }

    public function messageBetween(string $text): static
    {
        return $this->message('between', $text);
    }

    /* BOOLEAN */
    public function boolean(): static
    {
        return $this->rule('boolean');
    }

    public function messageBoolean(string $text): static
    {
        return $this->message('boolean', $text);
    }

    /* CONFIRMED */
    public function confirmed(): static
    {
        return $this->rule('confirmed');
    }

    public function messageConfirmed(string $text): static
    {
        return $this->message('confirmed', $text);
    }

    /* CONTAINS */
    public function contains(string|array $values): static
    {
        if (is_array($values)) {
            $values = implode(',', $values);
        }

        return $this->rule("contains:$values");
    }

    public function messageContains(string $text): static
    {
        return $this->message('contains', $text);
    }

    /* DOESNT_CONTAIN */
    public function doesntContain(string|array $values): static
    {
        if (is_array($values)) {
            $values = implode(',', $values);
        }

        return $this->rule("doesnt_contain:$values");
    }

    public function messageDoesntContain(string $text): static
    {
        return $this->message('doesnt_contain', $text);
    }

    /* CURRENT_PASSWORD */
    public function currentPassword(?string $guard = null): static
    {
        return $guard
            ? $this->rule("current_password:$guard")
            : $this->rule('current_password');
    }

    public function messageCurrentPassword(string $text): static
    {
        return $this->message('current_password', $text);
    }

    public function min(int $value): static
    {
        return $this->rule("min:$value");
    }

    public function max(int $value): static
    {
        return $this->rule("max:$value");
    }

    public function email(): static
    {
        return $this->rule('email');
    }

    public function getValidationRules(): array
    {
        return $this->rules;
    }

    public function getValidationMessages(): array
    {
        return $this->messages;
    }
}
