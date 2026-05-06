<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\Subscription;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register audit observers for critical models
        User::observe(AuditableObserver::class);
        Client::observe(AuditableObserver::class);
        Vehicle::observe(AuditableObserver::class);
        Subscription::observe(AuditableObserver::class);
    }
}
