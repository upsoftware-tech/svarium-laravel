<?php

namespace Upsoftware\Svarium\Providers;

use Illuminate\Support\AggregateServiceProvider;

class SvariumPluginAggregateServiceProvider extends AggregateServiceProvider
{
    public function __construct($app)
    {
        parent::__construct($app);

        $providers = [];
        //echo 1234; die;
        $this->providers = $providers;
    }
}
