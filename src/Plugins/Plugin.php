<?php

namespace Upsoftware\Svarium\Plugins;

abstract class Plugin
{
    protected float $version;
    protected string $name;
    protected array $dependencies = [];
    protected array $config = [];
}
