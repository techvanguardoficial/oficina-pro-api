<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        AuditService::logCreated($model);
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        // Only log if there are actual changes
        if ($model->isDirty()) {
            AuditService::logUpdated($model);
        }
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        // Log both soft and hard deletes
        AuditService::logDeleted($model);
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored(Model $model): void
    {
        AuditService::logRestored($model);
    }
}
