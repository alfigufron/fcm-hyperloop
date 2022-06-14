<?php

namespace App\Providers;

use App\Macros\SearchMacros;
use App\Macros\SortMacros;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Builder::mixin(new SortMacros());

        Builder::mixin(new SearchMacros());
    }
}
