<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use App\Helpers\CollectionHelper;

class CollectionMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Override the default unique() method with our optimized version
        Collection::macro('unique', function ($key = null, $strict = false) {
            return CollectionHelper::fastUnique($this, $key, $strict);
        });
    }
}