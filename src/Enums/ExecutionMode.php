<?php

namespace Upsoftware\Svarium\Enums;

enum ExecutionMode: string
{
    case VIEW = 'view';
    case FORM = 'form';
    case ACTION = 'action';
    case TABLE = 'table';
    case TREE = 'tree';
    case DUPLICATE = 'duplicate';
}
