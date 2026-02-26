<?php

namespace Upsoftware\Svarium\Http;

use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
class RedirectResult implements OperationResult
{
    protected string $url;

    protected array $flash = [];

    protected array $alert = [];

    public static function to(string $url): static
    {
        $instance = new static;
        $instance->url = $url;

        return $instance;
    }

    public function getFlash(): array
    {
        return $this->flash;
    }

    public function flash(string $type, string $message): static
    {
        $this->flash[$type] = $message;
        return $this;
    }

    public function alert(string $type, string $message): static
    {
        $this->alert[$type] = $message;
        return $this;
    }

    public function success(string $message, string $type = 'flash'): static
    {
        $this->{$type}('success', $message);
        return $this;
    }

    public function error(string $message, string $type = 'flash'): static
    {
        $this->{$type}('error', $message);
        return $this;
    }

    public function warning(string $message, string $type = 'flash'): static
    {
        $this->{$type}('warning', $message);
        return $this;
    }

    public function info(string $message, string $type = 'flash'): static
    {
        $this->{$type}('info', $message);
        return $this;
    }

    public function toResponse(): Response
    {
        // Inertia v2 obsługuje dedykowane flash data - ustawiamy również ten kanał.
        if (! empty($this->flash)) {
            Inertia::flash($this->flash);
        }

        return redirect($this->url)->with('flash', $this->flash);
    }
}
