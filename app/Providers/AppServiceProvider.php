<?php

namespace App\Providers;

use Core\BoundedContext\Admin\AppUser\Infrastructure\Repositories\AppUserRepository;
use Core\BoundedContext\Admin\User\Domain\Repositories\UserRepositoryInterface;
use Core\BoundedContext\Admin\User\Infrastructure\Persistence\Repositories\UserRepository;
use Core\BoundedContext\Customer\Process\Domain\Repositories\{
    OrganizationNotificationRepositoryInterface,
    OrganizationNotificationChannelRepositoryInterface,
    ProcessRepositoryInterface,
    HistoryOrganizationChannelNotificationRepositoryInterface
};
use Core\BoundedContext\Customer\Process\Infrastructure\Repositories\{
    OrganizationNotificationRepository,
    OrganizationNotificationChannelRepository,
    ProcessRepository,
    HistoryOrganizationChannelNotificationRepository
};
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
        $this->app->bind(OrganizationNotificationRepositoryInterface::class, OrganizationNotificationRepository::class);
        $this->app->bind(OrganizationNotificationChannelRepositoryInterface::class, OrganizationNotificationChannelRepository::class);
        $this->app->bind(HistoryOrganizationChannelNotificationRepositoryInterface::class, HistoryOrganizationChannelNotificationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
