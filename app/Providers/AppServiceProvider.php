<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\Subscription;
use App\Models\CarMaker;
use App\Models\CarModel;
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
        // Cadastro compartilhado: registra qual oficina/usuário criou
        CarMaker::observe(AuditableObserver::class);
        CarModel::observe(AuditableObserver::class);
    }
}
