<?php

namespace App\Providers;

use Core\BoundedContext\Admin\AppUser\Infrastructure\Repositories\AppUserRepository;
use Core\BoundedContext\Admin\User\Domain\Repositories\UserRepositoryInterface;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Repositories\UserRepository;
use Core\BoundedContext\Customer\Process\Domain\Repositories\ProcessRepositoryInterface;
use Core\BoundedContext\Customer\Process\Infrastructure\Repositories\ProcessRepository;
use Core\Shared\Domain\Repositories\AppUserRepositoryInterface;
use Core\Shared\Domain\Repositories\OrganizationRepositoryInterface;
use Core\Shared\Infrastructure\Repositories\OrganizationRepository;
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
        $this->app->bind(ProcessRepositoryInterface::class, ProcessRepository::class);
        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
