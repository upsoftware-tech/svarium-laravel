<?php

namespace Upsoftware\Svarium\Services;

use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Resources\Pages\BasePage;

class LayoutService
{
    protected string $title = '';
    protected array $breadcrumbs = [];

    public function set_title(string $title): void
    {
        $this->title = $title;
    }

    public function set_breadcrumb(array $breadcrumb): static
    {
        $this->breadcrumbs[] = $breadcrumb;
        return $this;
    }

    public function set_breadcrumbs(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function get_title(): string
    {
        return $this->title;
    }

    public function getComponents() : mixed {
        return $this->component();
    }

    public function component(string|array $component = '') : mixed
    {
        $pageClass = Route::current()->getControllerClass();
        $basePageClass = BasePage::class;

        if (is_subclass_of($pageClass, $basePageClass)) {
            if (!class_exists($pageClass)) {
                throw new \Exception("Component class not found: $pageClass");
            } else {
                $pageClassParams = explode('\\', $pageClass);
                $resource = $pageClassParams[3];
                $resourceClassPath = implode("\\", array_slice($pageClassParams, 0, 4))."\\".$resource."Resource";

                $componentKeys = $component;
                if ($componentKeys === '') {
                    $componentKeys = ['header', 'sidebar', 'content', 'footer'];
                }

                $components = [];
                $resourceClass = new $resourceClassPath;
                foreach ($componentKeys as $componentKey) {
                    $components[$componentKey] = [];
                    if ($pageClass::${$componentKey} !== null) {
                        $componentClassPath = $pageClass::${$componentKey};
                        if ($componentClassPath) {
                            $componentClass = new $componentClassPath;
                            $components[$componentKey] = $componentClass->getContent();
                        }
                    } else if ($resourceClass::${$componentKey} !== null) {
                        echo $resourceClass::${$componentKey};
                    }
                }
                return $components;
            }
        }
        return false;
    }
}
