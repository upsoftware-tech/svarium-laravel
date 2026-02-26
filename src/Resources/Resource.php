<?php

namespace Upsoftware\Svarium\Resources;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

abstract class Resource
{
    protected static ?string $model = null;
    protected static ?string $title = null;
    protected static ?string $pluralTitle = null;
    protected static ?string $routeName = null;
    protected static ?string $tableSchema = null;
    protected static ?string $formSchema = null;
    protected static ?string $layout = '';
    protected static string|array $permission = '';
    protected static string|array $role = '';

    public static function getTitle(): string
    {
        return static::$title ?? class_basename(static::$model);
    }

    public static function getPluralTitle(): string
    {
        return __('Specialists');
    }


    public static function getModel(): string
    {
        return static::$model;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function table(): array
    {
        return [];
    }

    public static function getRouteName(): string
    {
        return static::$routeName ?? strtolower(str_replace('Resource', '', class_basename(static::class)));
    }

    public static function getPages(): array
    {
        $pages = [];

        $reflection = new \ReflectionClass(static::class);
        $directory = dirname($reflection->getFileName()) . DIRECTORY_SEPARATOR . 'Pages';
        $subdirectories = collect(File::directories($directory))
            ->map(fn($path) => basename($path))
            ->toArray();

        foreach ($subdirectories as $subdirectory) {
            $directory = dirname($reflection->getFileName()) . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR . $subdirectory;
            if (!File::isDirectory($directory)) {
                return [];
            }

            $files = File::files($directory);

            foreach ($files as $file) {
                $className = $file->getBasename('.php');
                $fullClassName = $reflection->getNamespaceName() . "\\Pages\\" . $subdirectory . "\\" . $className;

                if (class_exists($fullClassName)) {
                    $routeKey = $fullClassName::getRouteName();
                    $routePath = $fullClassName::getRoutePath();
                    $routeWhereIn = $fullClassName::getRouteWhereIn();

                    $pages[$routeKey] = [
                        "area" => $subdirectory,
                        "className" => $fullClassName,
                        "route" => $routePath,
                        "routeWhereIn" => $routeWhereIn,
                    ];
                }
            }
        }

        return $pages;
    }

    public static function getTableSchema(): string
    {
        if (!static::$tableSchema) {
            throw new \Exception("Zasób " . static::class . " nie ma zdefiniowanego tableSchema.");
        }

        return static::$tableSchema;
    }

    public static function getFormSchema(): string
    {
        if (!static::$formSchema) {
            throw new \Exception("Zasób " . static::class . " nie ma zdefiniowanego formSchema.");
        }

        return static::$formSchema;
    }
}
