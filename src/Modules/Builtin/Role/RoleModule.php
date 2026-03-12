<?php

namespace Upsoftware\Svarium\Modules\Builtin\Role;

use Upsoftware\Svarium\Menu\MenuItem;
use Upsoftware\Svarium\Modules\Builtin\Role\Panel\RoleResource;
use Upsoftware\Svarium\Modules\Module;

class RoleModule extends Module
{
    public function name(): string
    {
        return 'Role';
    }

    public function menu(): array
    {
        return [
            MenuItem::make(svarium_label('modules.role.plural', __('Roles')))
                ->icon('lucide:shield-check')
                ->url('/'.ltrim(module_route('role'), '/'))
                ->order(91),
        ];
    }

    public function roleParameters(): array
    {
        return [
            'languages' => [
                'label' => __('Languages'),
                'description' => __('Access to selected interface languages.'),
                'options' => static fn (): array => collect(locales())
                    ->map(static fn (array $locale): array => [
                        'value' => (string) ($locale['value'] ?? ''),
                        'label' => (string) ($locale['label'] ?? ($locale['value'] ?? '')),
                    ])
                    ->filter(static fn (array $locale): bool => $locale['value'] !== '')
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function register(): void
    {
        $this->registerResource(RoleResource::class);
    }
}
