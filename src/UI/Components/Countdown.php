<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Countdown extends Component
{
    public function seconds(int|string $seconds): static
    {
        return $this->prop('seconds', max(0, (int) $seconds));
    }

    public function template(string|Component|array $template): static
    {
        if (is_string($template)) {
            return $this->prop('template', $template);
        }

        return $this->prop('templateComponents', $this->normalizeTemplateComponents($template));
    }

    public function afterText(string $text): static
    {
        return $this->prop('afterText', $text);
    }

    public function afterUrl(string $url): static
    {
        return $this->prop('afterUrl', trim($url));
    }

    public function url(string $url): static
    {
        return $this->afterUrl($url);
    }

    public function panelHref(string $path = '', ?string $panel = null): static
    {
        return $this->afterUrl(panel_href($path, $panel));
    }

    public function action(string $text, ?string $url = null): static
    {
        $this->afterText($text);

        if ($url !== null) {
            $this->afterUrl($url);
        }

        return $this;
    }

    protected function normalizeTemplateComponents(Component|array $template): array
    {
        $items = $template instanceof Component
            ? [$template]
            : $template;

        $normalized = [];

        foreach ($items as $item) {
            if ($item instanceof Component) {
                if (method_exists($item, 'shouldRender') && ! $item->shouldRender()) {
                    continue;
                }

                $normalized[] = $item->toArray();
                continue;
            }

            if (is_array($item) && isset($item['type'])) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }
}
