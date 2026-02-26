<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Http\Request;

class PanelContext
{
    public array $params = [];

    public array $validated = [];

    protected ?string $operationType = null;

    protected Panel $panel;

    protected Request $request;

    public function __construct(
        Panel $panel,
        Request $request,
        array $params = []
    ) {
        $this->panel = $panel;
        $this->request = $request;
        $this->params = $params;
    }

    public function panel(): Panel
    {
        return $this->panel;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function isGet(): bool
    {
        return $this->request->isMethod('GET');
    }

    public function setOperationType(string $type): void
    {
        $this->operationType = $type;
    }

    public function operationType(): ?string
    {
        return $this->operationType;
    }

    public function isPost(): bool
    {
        return $this->request->isMethod('POST');
    }

    public function input(?string $key = null, $default = null)
    {
        return $this->request->input($key, $default);
    }

    public function all(): array
    {
        return $this->request->all();
    }

    public function validate(array $rules): array
    {
        $this->validated = validator(
            $this->request->all(),
            $rules
        )->validate();

        return $this->validated;
    }

    public function validated(): array
    {
        return $this->validated;
    }
}
