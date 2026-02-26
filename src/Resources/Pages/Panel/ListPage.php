<?php

namespace Upsoftware\Svarium\Resources\Pages\Panel;

use Inertia\Inertia;
use Inertia\Response;
use Upsoftware\Svarium\Resources\Pages\BasePage;

class ListPage extends BasePage
{
    protected static ?string $pageType = 'table';
    protected static ?string $routeName = 'index';

    public function __invoke(...$params): Response
    {
        $data = [];
        print_r($this->request);
        return Inertia::render(static::getPage(), $data);
    }
}
