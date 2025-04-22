<?php

namespace Csaba215\Mnb\Laravel;

use Illuminate\Support\ServiceProvider;

class MnbServiceProvider extends ServiceProvider
{
    /**
     * Register service provider
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'mnb-exchange');
        $this->app->alias(Client::class, 'mnb.client');
    }

    /**
     * Boot service provider
     */
    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/config.php'], 'config');
    }
}