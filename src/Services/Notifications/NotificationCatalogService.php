<?php

namespace Upsoftware\Svarium\Services\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class NotificationCatalogService
{
    /**
     * @return array<int, array{
     *   id:string,
     *   key:string,
     *   class:string,
     *   label:string,
     *   source:string,
     *   source_key:string,
     *   file:string,
     *   placeholders:array<int,string>
     * }>
     */
    public function all(): array
    {
        $rows = [];
        $seen = [];

        foreach ($this->sources() as $source) {
            $basePath = (string) ($source['base_path'] ?? '');
            if ($basePath === '' || ! is_dir($basePath)) {
                continue;
            }

            $sourceKey = (string) ($source['key'] ?? 'custom');
            $sourceLabel = (string) ($source['label'] ?? $sourceKey);

            foreach (File::allFiles($basePath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                if (! $this->looksLikeNotificationFile($path)) {
                    continue;
                }

                $class = $this->resolveClassFromFile($path);
                if ($class === null) {
                    continue;
                }

                if (isset($seen[$class])) {
                    continue;
                }

                if (! $this->isNotificationClass($class, $path)) {
                    continue;
                }

                $seen[$class] = true;

                $rows[] = [
                    'id' => $this->idForClass($class),
                    'key' => $this->resolveTemplateKey($class),
                    'class' => $class,
                    'label' => $this->resolveLabel($class),
                    'source' => $sourceLabel,
                    'source_key' => $sourceKey,
                    'file' => $path,
                    'placeholders' => $this->resolvePlaceholders($class),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $sourceCompare = strcmp($a['source'], $b['source']);
            if ($sourceCompare !== 0) {
                return $sourceCompare;
            }

            return strcmp($a['label'], $b['label']);
        });

        return array_values($rows);
    }

    /**
     * @return array{
     *   id:string,
     *   key:string,
     *   class:string,
     *   label:string,
     *   source:string,
     *   source_key:string,
     *   file:string,
     *   placeholders:array<int,string>
     * }|null
     */
    public function findById(string $id): ?array
    {
        $needle = trim($id);
        if ($needle === '') {
            return null;
        }

        foreach ($this->all() as $item) {
            if ((string) ($item['id'] ?? '') === $needle) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{key:string,label:string,base_path:string}>
     */
    protected function sources(): array
    {
        $sources = [
            [
                'key' => 'system',
                'label' => __('System'),
                'base_path' => __DIR__.'/../../Notifications',
            ],
            [
                'key' => 'app',
                'label' => __('Aplikacja'),
                'base_path' => app_path('Notifications'),
            ],
        ];

        $modulesBase = svarium_modules();
        if (is_dir($modulesBase)) {
            foreach (File::directories($modulesBase) as $moduleDir) {
                $moduleName = basename($moduleDir);
                if ($moduleName === '') {
                    continue;
                }

                $sources[] = [
                    'key' => 'module:'.Str::lower($moduleName),
                    'label' => 'Moduł: '.$moduleName,
                    'base_path' => $moduleDir,
                ];
            }
        }

        $sources[] = [
            'key' => 'svarium',
            'label' => 'App/Svarium',
            'base_path' => svarium_path(),
        ];

        return $sources;
    }

    protected function looksLikeNotificationFile(string $path): bool
    {
        $filename = basename($path);

        if (str_contains($filename, 'Notification') || str_contains($filename, 'Notify')) {
            return true;
        }

        if (str_contains($path, DIRECTORY_SEPARATOR.'Notifications'.DIRECTORY_SEPARATOR)) {
            return true;
        }

        return false;
    }

    protected function resolveClassFromFile(string $path): ?string
    {
        $content = @file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $namespaceMatch) !== 1) {
            return null;
        }

        if (
            preg_match(
                '/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m',
                $content,
                $classMatch
            ) !== 1
        ) {
            return null;
        }

        $namespace = trim($namespaceMatch[1]);
        $class = trim($classMatch[1]);

        if ($namespace === '' || $class === '') {
            return null;
        }

        return trim($namespace, '\\').'\\'.$class;
    }

    protected function isNotificationClass(string $class, string $path): bool
    {
        if (! class_exists($class)) {
            try {
                require_once $path;
            } catch (Throwable) {
                return false;
            }
        }

        if (! class_exists($class)) {
            return false;
        }

        if (! is_subclass_of($class, Notification::class)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($class);

            return ! $reflection->isAbstract();
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveTemplateKey(string $class): string
    {
        $key = (string) Str::of(class_basename($class))
            ->replace(['Notification', 'Notify'], '')
            ->snake()
            ->trim('_');

        if ($key !== '') {
            return $key;
        }

        return (string) Str::of(class_basename($class))->snake();
    }

    protected function resolveLabel(string $class): string
    {
        $label = (string) Str::of(class_basename($class))
            ->replace(['Notification', 'Notify'], '')
            ->headline()
            ->trim();

        return $label !== '' ? $label : class_basename($class);
    }

    protected function idForClass(string $class): string
    {
        return sha1($class);
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePlaceholders(string $class): array
    {
        $basename = class_basename($class);

        return match ($basename) {
            'SendCodeNotificationEmailLogin',
            'SendCodeNotificationEmailRegister',
            'SendCodeNotificationEmailReset' => ['code', 'expires', 'system'],
            'LoginFromNewDeviceNotify' => ['ip', 'deviceType', 'platform', 'platformVer', 'browser', 'browserVer', 'system'],
            'UserChangePasswordNotify' => ['system'],
            default => ['system'],
        };
    }
}
