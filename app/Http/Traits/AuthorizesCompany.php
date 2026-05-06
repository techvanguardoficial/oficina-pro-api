<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Model;

trait AuthorizesCompany
{
    protected function authorizeCompany(Model $model): void
    {
        if ($model->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized');
        }
    }
}
