<?php

namespace App\Providers;

use App\Contracts\EventSearch;
use App\Contracts\NotificationProvider;
use App\Infrastructure\Notifications\HttpNotificationProvider;
use App\Infrastructure\Search\MysqlEventSearch;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationProvider::class, HttpNotificationProvider::class);
        $this->app->bind(EventSearch::class, MysqlEventSearch::class);
    }

    public function boot(): void
    {
        // Intentionally empty.
    }
}
