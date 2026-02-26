<?php

namespace Upsoftware\Svarium\Resources\Pages;

use Illuminate\Support\Facades\Cache;
use Upsoftware\Svarium\Resources\Enums\PagePath;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;

abstract class BasePage
{
    protected static ?string $resource = null;
    protected static ?string $pageType = 'table';
    protected static ?string $page = null;
    protected static ?string $method = 'get';
    protected static ?string $action = '__invoke';
    protected static ?string $routePath = null;
    protected static ?array $routeWhereIn = [];
    protected ?array $request = [];
    public static ?string $layout = '';
    public static ?string $beforeHeader;
    public static ?string $header = null;
    public static ?string $afterHeader;
    public static ?string $sidebar = '';
    public static ?string $beforeFooter;
    public static ?string $footer = '';
    public static ?string $afterFooter;
    public static ?string $beforeContent;
    public static ?string $content = '';
    public static ?string $afterContent;

    /**
     * @throws \Exception
     */
    public function __construct() {
        if (!static::$resource) {
            static::$resource = static::getResource();
        }

        $route = request()->route();
        $params = array_merge(
            request()->all(),
            $route->parameters()
        );
        $this->request = $params;
    }

    public static function getPage(): string
    {
        if (static::$page) {
            return static::$page;
        }

        return PagePath::from(static::$pageType)->getPagePath();
    }

    public static function getMethod(): string
    {
        return static::$method;
    }

    public static function getAction(): string
    {
        return static::$action;
    }

    public static function getRoutePath(): string|null
    {
        return static::$routePath;
    }

    public static function getRouteWhereIn(): array
    {
        $cacheKey = 'route_where_in_' . Str::snake(class_basename(static::class));
        return Cache::remember($cacheKey, 3600, function () {
            $staticWhere = static::$routeWhereIn ?? [];
            if (isset($staticWhere[0]) && is_array($staticWhere[0])) {
                $staticWhere = $staticWhere[0];
            }

            $dynamicWhere = [];
            if (method_exists(static::class, 'setRouteWhereIn')) {
                $dynamicWhere = static::setRouteWhereIn();
            }

            return array_merge($staticWhere, $dynamicWhere);
        });
    }

    public static function getRouteName(): string
    {
        return static::$routeName ?? Str::kebab(class_basename(static::class));
    }

    public static function getResource(): string
    {
        $reflection = new ReflectionClass(static::class);
        $namespace = $reflection->getNamespaceName();
        $resourceNamespace = Str::beforeLast($namespace, '\\Pages');
        $resourceName = Str::afterLast($resourceNamespace, '\\');
        $resourceClass = $resourceNamespace . "\\" . $resourceName . "Resource";
        if (!class_exists($resourceClass)) {
            throw new \Exception("Nie udało się automatycznie wykryć Resource dla strony " . static::class . ". Oczekiwano klasy: " . $resourceClass);
        }

        return $resourceClass;
    }

    protected function resolveSchema(): array
    {
        if (static::getRouteName() === 'index') {
            return $this->resolveTableSchema();
        }

        return $this->resolveFormSchema();
    }

    protected function resolveFormSchema(): array
    {
        $resource = static::getResource();
        $schemaClass = $resource::getFormSchema();
        return $schemaClass::make()->render(static::getRouteName());
    }

    protected function resolveTableSchema(): array
    {
        $resource = static::getResource();
        $schemaClass = $resource::getTableSchema();
        return $schemaClass::make()->render(static::getRouteName());
    }

    public function request(string $param, $default = null) : string|null
    {
        return $this->request[$param] ?? $default;
    }

    public function __invoke(...$params): mixed
    {

    }

}
