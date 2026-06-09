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
        'vehicle_id',
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
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_services_id');
    }
}
