<?php

namespace App\Providers;

use App\Models\ProductionOrder;
use App\Observers\ProductionOrderObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */


    public function boot()
    {
        ProductionOrder::observe(ProductionOrderObserver::class);
    }
}
