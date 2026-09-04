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
    ];

    protected $casts = [
        'interval_km'     => 'integer',
        'interval_months' => 'integer',
    ];
}
