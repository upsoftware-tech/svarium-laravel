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
    protected array $layoutProps = [];

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

    public function layoutProp(string $key, mixed $value): static
    {
        $this->layoutProps[$key] = $value;

        return $this;
    }

    public function layoutProps(array $props): static
    {
        foreach ($props as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $this->layoutProp($key, $value);
        }

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
        $layoutClass = $this->layoutClass ?? PanelLayout::class;

        $layout = app($layoutClass);

        if (!$layout instanceof Component) {
            throw new \RuntimeException("Layout [$layoutClass] must extend Component.");
        }

        if ($this->layoutProps !== []) {
            foreach ($this->layoutProps as $key => $value) {
                $layout->prop($key, $value);
            }
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
        $resolvedContent = $pageContent;

        if ($panelWrapper) {

            $wrapper = is_string($panelWrapper)
                ? app($panelWrapper)
                : $panelWrapper;

            if ($wrapper instanceof \Upsoftware\Svarium\UI\Component) {
                $wrapper->slot('content', $pageContent);
                $resolvedContent = $wrapper;
            } else {
                $resolvedContent = $pageContent;
            }
        }

        $layoutTree = $layout->toArray();
        $contentNodes = $this->normalizeNodes($resolvedContent);
        $hasExistingBodyContent = $this->layoutTreeHasBodyContent($layoutTree);

        if (! $this->injectContentIntoLayoutTree($layoutTree, $contentNodes)) {
            // If a layout already defines its own body/content structure, keep it.
            // Dynamic page content should be injected via Body placeholder.
            if (! $hasExistingBodyContent) {
                if (method_exists($layout, 'body')) {
                    $layout->slot('body', $resolvedContent);
                } else {
                    $layout->content($this->normalizeForContent($resolvedContent));
                }

                $layoutTree = $layout->toArray();
            }
        }

        $layoutTree = $this->wrapWithRootLayout($layoutTree);

        $view = $this->view ?? 'Svarium';

        return Inertia::render($view, array_merge([
            'tree' => $layoutTree,
            'meta' => $this->meta,
        ], $this->props))->toResponse(request());
    }

    protected function injectContentIntoLayoutTree(array &$layoutTree, array $contentNodes): bool
    {
        $injected = false;

        if (isset($layoutTree['slots']['body']) && is_array($layoutTree['slots']['body'])) {
            $layoutTree['slots']['body'] = $this->injectIntoNodes(
                $layoutTree['slots']['body'],
                $contentNodes,
                $injected
            );
        }

        if ($injected) {
            return true;
        }

        if (isset($layoutTree['slots']['content']) && is_array($layoutTree['slots']['content'])) {
            $layoutTree['slots']['content'] = $this->injectIntoNodes(
                $layoutTree['slots']['content'],
                $contentNodes,
                $injected
            );
        }

        if ($injected) {
            return true;
        }

        if (isset($layoutTree['children']) && is_array($layoutTree['children'])) {
            $layoutTree['children'] = $this->injectIntoNodes(
                $layoutTree['children'],
                $contentNodes,
                $injected
            );
        }

        return $injected;
    }

    protected function injectIntoNodes(array $nodes, array $contentNodes, bool &$injected): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'Body') {
                $injected = true;

                $bodyNode = $node;
                $bodyProps = is_array($bodyNode['props'] ?? null)
                    ? $bodyNode['props']
                    : [];
                $existingChildren = is_array($bodyNode['children'] ?? null)
                    ? $bodyNode['children']
                    : [];
                $resolvedContentNodes = $this->applyBodyPropsToContentNodes(
                    $contentNodes,
                    $bodyProps
                );

                // Body is a logical placeholder. Replace it with concrete content.
                foreach ([...$existingChildren, ...$resolvedContentNodes] as $resolvedNode) {
                    if (is_array($resolvedNode)) {
                        $result[] = $resolvedNode;
                    }
                }
                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->injectIntoNodes($node['children'], $contentNodes, $injected);
            }

            if (isset($node['slots']) && is_array($node['slots'])) {
                foreach ($node['slots'] as $slotName => $slotNodes) {
                    if (! is_array($slotNodes)) {
                        continue;
                    }

                    $node['slots'][$slotName] = $this->injectIntoNodes($slotNodes, $contentNodes, $injected);
                }
            }

            $result[] = $node;
        }

        return $result;
    }

    protected function normalizeNodes(mixed $content): array
    {
        if (is_string($content) && class_exists($content)) {
            $content = app($content);
        }

        if ($content instanceof Component) {
            return [$content->toArray()];
        }

        if (! is_array($content)) {
            return [];
        }

        $nodes = [];

        foreach ($content as $node) {
            if ($node instanceof Component) {
                $nodes[] = $node->toArray();
                continue;
            }

            if (is_array($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    protected function normalizeForContent(mixed $content): Component|array
    {
        if (is_string($content) && class_exists($content)) {
            $content = app($content);
        }

        if ($content instanceof Component) {
            return $content;
        }

        if (is_array($content)) {
            return $content;
        }

        return [];
    }

    protected function wrapWithRootLayout(array $layoutTree): array
    {
        $rootLayout = trim((string) config('upsoftware.panel.root_layout', 'CleanLayout'));
        if ($rootLayout === '') {
            return $layoutTree;
        }

        $currentType = trim((string) ($layoutTree['type'] ?? ''));
        if ($currentType === $rootLayout) {
            return $layoutTree;
        }

        $definitionTypes = config('upsoftware.panel.definition_layout_types', ['AuthLayout']);
        if (! is_array($definitionTypes)) {
            $definitionTypes = ['AuthLayout'];
        }

        $children = [$layoutTree];

        if (in_array($currentType, $definitionTypes, true)) {
            $flattened = $this->extractLayoutDefinitionNodes($layoutTree);
            if ($flattened !== []) {
                $children = $flattened;
            }
        }

        return [
            'type' => $rootLayout,
            'props' => [],
            'children' => $children,
            'slots' => [],
        ];
    }

    protected function extractLayoutDefinitionNodes(array $layoutTree): array
    {
        $slots = is_array($layoutTree['slots'] ?? null) ? $layoutTree['slots'] : [];

        $orderedSlots = [
            'header',
            'sidebar',
            'contentHeader',
            'body',
            'content',
            'contentFooter',
            'aside',
            'footer',
        ];

        $flattened = [];

        foreach ($orderedSlots as $slotName) {
            $slotNodes = $slots[$slotName] ?? null;

            if (! is_array($slotNodes) || $slotNodes === []) {
                continue;
            }

            foreach ($slotNodes as $node) {
                if (is_array($node)) {
                    $flattened[] = $node;
                }
            }
        }

        if ($flattened !== []) {
            return $flattened;
        }

        $children = is_array($layoutTree['children'] ?? null) ? $layoutTree['children'] : [];

        return $children;
    }

    protected function layoutTreeHasBodyContent(array $layoutTree): bool
    {
        $slots = is_array($layoutTree['slots'] ?? null) ? $layoutTree['slots'] : [];

        $body = $slots['body'] ?? null;
        if (is_array($body) && $body !== []) {
            return true;
        }

        $content = $slots['content'] ?? null;
        if (is_array($content) && $content !== []) {
            return true;
        }

        $children = $layoutTree['children'] ?? null;

        return is_array($children) && $children !== [];
    }

    protected function applyBodyPropsToContentNodes(array $contentNodes, array $bodyProps): array
    {
        if ($bodyProps === []) {
            return $contentNodes;
        }

        if (count($contentNodes) === 1 && is_array($contentNodes[0])) {
            $node = $contentNodes[0];
            $nodeProps = is_array($node['props'] ?? null) ? $node['props'] : [];
            $node['props'] = $this->mergeNodeProps($nodeProps, $bodyProps);

            return [$node];
        }

        return [[
            'type' => 'Block',
            'props' => $bodyProps,
            'children' => $contentNodes,
            'slots' => [],
        ]];
    }

    protected function mergeNodeProps(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if ($key === 'appearance') {
                $currentAppearance = is_array($merged['appearance'] ?? null)
                    ? $merged['appearance']
                    : [];
                $incomingAppearance = is_array($value) ? $value : [];
                $merged['appearance'] = $this->mergeAppearanceProps(
                    $currentAppearance,
                    $incomingAppearance
                );
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    protected function mergeAppearanceProps(array $current, array $incoming): array
    {
        $merged = [...$current, ...$incoming];

        $currentClass = $current['class'] ?? '';
        $incomingClass = $incoming['class'] ?? '';
        $mergedClass = $this->mergeClassTokens($currentClass, $incomingClass);

        if ($mergedClass !== '') {
            $merged['class'] = $mergedClass;
        }

        return $merged;
    }

    protected function mergeClassTokens(mixed $current, mixed $incoming): string
    {
        $tokens = [];
        $allTokens = array_merge(
            preg_split('/\s+/', trim((string) $current)) ?: [],
            preg_split('/\s+/', trim((string) $incoming)) ?: []
        );

        foreach ($allTokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (! in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return implode(' ', $tokens);
    }
}
