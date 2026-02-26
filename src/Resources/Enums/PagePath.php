<?php

namespace Upsoftware\Svarium\Resources\Enums;

enum PagePath: string
{
    case Table = 'table';
    case Category = 'category';
    case Form = 'form';

    public function getPagePath(): string {
        return match($this) {
            self::Table => 'Resources/Table',
            self::Category => 'Resources/Category',
            self::Form => 'Resources/Form',
        };
    }
}
