<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleMaintenanceSchedule extends Model
{
    protected $fillable = [
        'client_app_user_id',
        'placa',
        'description',
        'interval_km',
        'interval_months',
        'last_done_at',
        'last_done_km',
    ];

    protected $casts = [
        'interval_km'     => 'integer',
        'interval_months' => 'integer',
        'last_done_at'    => 'date:Y-m-d',
        'last_done_km'    => 'integer',
    ];
}
