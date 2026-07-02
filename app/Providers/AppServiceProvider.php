<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
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
    public function boot(): void
    {
        // The ERP admin uses its own stylesheet, not Tailwind, so render
        // pagination with the ERP-styled view.
        Paginator::defaultView('pagination::erp');
        Paginator::defaultSimpleView('pagination::erp');
        Schema::defaultStringLength(191);
    }
}
