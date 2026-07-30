<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface::class,
            \App\Modules\Ticketing\Infrastructure\Repositories\TicketRepository::class
        );

        $this->app->bind(
            \App\Modules\Organization\Domain\Repositories\EmployeeDirectoryRepositoryInterface::class,
            \App\Modules\Organization\Infrastructure\Repositories\EmployeeDirectoryRepository::class
        );

        $this->app->bind(
            \App\Modules\Asset\Domain\Repositories\AssetRepositoryInterface::class,
            \App\Modules\Asset\Infrastructure\Repositories\AssetRepository::class
        );

        $this->app->bind(
            \App\Modules\SLA\Domain\Repositories\SlaRepositoryInterface::class,
            \App\Modules\SLA\Infrastructure\Repositories\SlaRepository::class
        );

        $this->app->bind(
            \App\Modules\Knowledge\Domain\Repositories\KnowledgeRepositoryInterface::class,
            \App\Modules\Knowledge\Infrastructure\Repositories\KnowledgeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
