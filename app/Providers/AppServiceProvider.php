<?php

namespace App\Providers;

use Core\BoundedContext\Admin\AppUser\Infrastructure\Repositories\AppUserRepository;
use Core\BoundedContext\Admin\User\Domain\Repositories\UserRepositoryInterface;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Repositories\UserRepository;
use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AppUserRepositoryInterface::class, AppUserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
