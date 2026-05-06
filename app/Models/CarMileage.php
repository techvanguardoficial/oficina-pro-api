<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarMileage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'mileage',
        'vehicles_placa',
        'order_services_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicles_placa', 'placa');
    }

    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_services_id');
    }
}
