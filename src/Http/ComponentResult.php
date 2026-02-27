<?php

namespace Upsoftware\Svarium\Http;

use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Layouts\PanelLayout;

class ComponentResult implements OperationResult
{
    protected ?string $view = null;
    protected array $meta = [];

    public function __construct(
        protected Component $component,
        protected ?string $layoutClass = null
    ) {}

    protected array $slotOverrides = [];
    protected array $props = [];

    public function setLayout(?string $layout): void
    {
        $this->layoutClass = $layout;
    }

    public function setView(?string $view): void
    {
        $this->view = $view;
    }

    public function meta(string $key, $value): static
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function prop(string $key, mixed $value): static
    {
        $this->props[$key] = $value;
        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function header($content): static
    {
        $this->slotOverrides['header'] = $content;
        return $this;
    }

    public function content($content): static
    {
        $this->slotOverrides['content'] = $content;
        return $this;
    }

    public function contentHeader($content): static
    {
        $this->slotOverrides['contentHeader'] = $content;
        return $this;
    }

    public function contentFooter($content): static
    {
        $this->slotOverrides['contentFooter'] = $content;
        return $this;
    }

    public function sidebar($content): static
    {
        $this->slotOverrides['sidebar'] = $content;
        return $this;
    }

    public function aside($content): static
    {
        $this->slotOverrides['aside'] = $content;
        return $this;
    }

    public function footer($content): static
    {
        $this->slotOverrides['footer'] = $content;
        return $this;
    }

    public function toResponse(): Response
    {
        $root = $this->component;

        $layoutClass = $this->layoutClass ?? PanelLayout::class;

        $layout = app($layoutClass);

        if (!$layout instanceof Component) {
            throw new \RuntimeException("Layout [$layoutClass] must extend Component.");
        }

        $panelName = request()->attributes->get('panel');

        $panel = $panelName
            ? app(\Upsoftware\Svarium\Panel\PanelRegistry::class)->get($panelName)
            : null;

        /* 1. layoutUsing */
        if ($panel?->layoutBuilder) {
            ($panel->layoutBuilder)($layout);
        }

        /* 2. Panel slots (bez body/content) */
        $panelSlots = $panel?->getLayoutSlots() ?? [];

        //dd($panel?->getLayoutSlots());

        foreach ($panelSlots as $slot => $value) {
            if (in_array($slot, ['content','body'])) continue;

            if (method_exists($layout, $slot)) {
                $layout->{$slot}($value);
            }
        }

        /* 3. Operation overrides (bez body/content) */
        foreach ($this->slotOverrides as $slot => $value) {
            if (in_array($slot, ['content','body'])) continue;

            if (method_exists($layout, $slot)) {
                $layout->{$slot}($value);
            }
        }

        /* 4. --- TERAZ dopiero budujemy BODY --- */

        $pageContent = $this->component;

        if (array_key_exists('content', $this->slotOverrides)) {
            $pageContent = $this->slotOverrides['content'];
        }

        $panelWrapper = $panelSlots['body'] ?? null;

        if ($panelWrapper) {

            $wrapper = is_string($panelWrapper)
                ? app($panelWrapper)
                : $panelWrapper;

            if ($wrapper instanceof \Upsoftware\Svarium\UI\Component) {
                $wrapper->slot('content', $pageContent);
                $layout->slot('body', $wrapper);
            } else {
                $layout->slot('body', $pageContent);
            }

        } else {
            $layout->slot('body', $pageContent);
        }

        $view = $this->view ?? 'Svarium';

        return Inertia::render($view, array_merge([
            'tree' => $layout->toArray(),
            'meta' => $this->meta,
        ], $this->props))->toResponse(request());
    }
}
