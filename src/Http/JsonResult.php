<?php

namespace Upsoftware\Svarium\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonResult implements OperationResult
{
    protected int $status = 200;

    public function __construct(
        protected array $payload = []
    ) {}

    public static function make(array $payload = [], int $status = 200): static
    {
        $instance = new static($payload);
        $instance->status($status);

        return $instance;
    }

    public function status(int $status): static
    {
        $this->status = max(100, min(599, $status));

        return $this;
    }

    public function toResponse(): Response
    {
        return new JsonResponse($this->payload, $this->status);
    }
}
