<?php

namespace App\Providers;

use App\Support\InternalToolAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(fn (Request $request): bool => InternalToolAuth::authorize($request));
    }

    /**
     * Required by HorizonApplicationServiceProvider (used if Horizon::auth is not set).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => false);
    }
}
