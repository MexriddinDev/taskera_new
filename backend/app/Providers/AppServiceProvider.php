<?php

namespace App\Providers;

use App\Modules\Telegram\Infrastructure\Listeners\SyncTelegramThreadListener;
use App\Modules\Ticketing\Domain\Events\CommentAdded;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
            \App\Modules\Knowledge\Domain\Repositories\KnowledgeRepositoryInterface::class,
            \App\Modules\Knowledge\Infrastructure\Repositories\KnowledgeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // N+1 so'rovlarga qarshi qo'riqchi.
        //
        // `with()` unutilgan ro'yxat sahifasi jimgina 100+ so'rov yuboradi va
        // buni hech kim sezmaydi. Endi har bir bunday holat log'ga tushadi:
        //   storage/logs/laravel.log -> "[N+1] ... Ticket::requesterEmployee"
        //
        // Ataylab XATO TASHLANMAYDI — tizim ishlayotgan holatda sahifani
        // buzib qo'ymaslik uchun. Rivojlantirishda qat'iyroq kerak bo'lsa,
        // quyidagi `handleLazyLoadingViolationUsing` blokini o'chirish kifoya:
        // shunda lokal muhitda xato tashlanadi.
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('[N+1] Eager loading unutilgan: '.$model::class.'::'.$relation);
        });

        // Zayavka hodisalarini Telegram bildirishnomalariga ulash
        Event::listen([
            TicketStatusChanged::class,
            TicketAssigned::class,
            TicketCreated::class,
            CommentAdded::class,
        ], SyncTelegramThreadListener::class);
    }
}
