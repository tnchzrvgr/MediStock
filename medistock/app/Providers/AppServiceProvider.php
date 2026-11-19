<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // load our small helper functions (status_color, money, ...)
        require_once app_path('helpers.php');
    }
}
