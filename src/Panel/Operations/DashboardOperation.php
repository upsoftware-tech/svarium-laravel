<?php

namespace Upsoftware\Svarium\Panel\Operations;

use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;

class DashboardOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return '';
    }

    public static function methods(): array
    {
        return ['GET'];
    }

    public function title(): string
    {
        return __('Dashboard');
    }

    public function schema(PanelContext $context): array
    {
        $this->applyTitleIfEmpty($this->title());

        return [];
    }

    protected function applyTitleIfEmpty(string $title): void
    {
        if (! function_exists('set_title') || ! function_exists('get_title')) {
            return;
        }

        if (trim((string) get_title()) !== '') {
            return;
        }

        set_title($title);
    }
}
