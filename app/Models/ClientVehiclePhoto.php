<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientVehiclePhoto extends Model
{
    protected $fillable = ['client_app_user_id', 'placa', 'photo_path'];
}
