<?php

namespace App\Providers;

use App\Http\Middleware\EnsureRoleIsAdmin;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('firebase.auth', EnsureTokenIsValid::class);
        $this->app['router']->aliasMiddleware('is.admin', EnsureRoleIsAdmin::class);

    }
}
