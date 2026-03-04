<?php

namespace Upsoftware\Svarium\Panel\Operations;

use Illuminate\Support\Facades\Artisan;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\Text;

class SvariumInstallOperation extends Operation
{
    public static string|array $panels = '*';

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public static function uri(): string
    {
        return 'svarium/install';
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    protected function formActions(): array
    {
        return [
            Button::make(__('Open configuration'))
                ->type('submit')
                ->name('_action')
                ->value('open_configuration'),
            Button::make(__('Run migrate'))
                ->type('submit')
                ->name('_action')
                ->value('migrate'),
            Button::make(__('Run optimize:clear'))
                ->type('submit')
                ->name('_action')
                ->value('optimize_clear'),
            Button::make(__('Run native:install'))
                ->type('submit')
                ->name('_action')
                ->value('native_install'),
            Button::make(__('Install tenant DB config'))
                ->type('submit')
                ->name('_action')
                ->value('install_tenant_config'),
        ];
    }

    public function title(PanelContext $context): string
    {
        return __('Svarium install');
    }

    public function schema(PanelContext $context): array
    {
        return [
            Text::make(__('Svarium installer'))->headline('h2')->appearance('text-lg font-semibold'),
            Text::make(__('Use quick actions below or open full configuration page.'))->appearance('text-sm'),
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $action = trim((string) $context->input->get('_action', ''));

        if ($action === 'open_configuration') {
            return RedirectResult::to($this->configurationUrl($context));
        }

        if ($action === 'migrate') {
            Artisan::call('migrate', ['--force' => true]);

            return RedirectResult::to($this->url($context))
                ->success(__('Database migrations have been executed.'));
        }

        if ($action === 'optimize_clear') {
            Artisan::call('optimize:clear');

            return RedirectResult::to($this->url($context))
                ->success(__('Laravel cache has been cleared.'));
        }

        if ($action === 'native_install') {
            Artisan::call('native:install');

            return RedirectResult::to($this->url($context))
                ->success(__('native:install has been executed.'));
        }

        if ($action === 'install_tenant_config') {
            Artisan::call('svarium:tenant.install', ['--no-interaction' => true]);

            return RedirectResult::to($this->url($context))
                ->success(__('Tenant database configuration has been installed.'));
        }

        return RedirectResult::to($this->url($context))
            ->warning(__('No installer action has been selected.'));
    }

    protected function url(PanelContext $context): string
    {
        $prefix = trim($context->panel()->prefixName(), '/');

        return $prefix !== ''
            ? "{$prefix}/svarium/install"
            : 'svarium/install';
    }

    protected function configurationUrl(PanelContext $context): string
    {
        $prefix = trim($context->panel()->prefixName(), '/');

        return $prefix !== ''
            ? "{$prefix}/svarium/configuration"
            : 'svarium/configuration';
    }
}
