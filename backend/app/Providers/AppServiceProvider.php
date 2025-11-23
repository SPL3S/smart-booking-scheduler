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
            \App\Repositories\BookingRepositoryInterface::class,
            \App\Repositories\BookingRepository::class
        );

        $this->app->bind(
            \App\Repositories\ServiceRepositoryInterface::class,
            \App\Repositories\ServiceRepository::class
        );

        $this->app->bind(
            \App\Repositories\WorkingHourRepositoryInterface::class,
            \App\Repositories\WorkingHourRepository::class
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
