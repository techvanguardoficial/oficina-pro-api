<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'info',
        'vehicle_placa',
        'orders_types_id',
        'orders_status_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_placa', 'placa');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrderType::class, 'orders_types_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'orders_status_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'orders_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'orders_id');
    }

    public function mileages(): HasMany
    {
        return $this->hasMany(CarMileage::class, 'order_services_id');
    }

    // Accessors
    public function getTotalPartsAttribute(): float
    {
        return $this->parts->sum('price') ?? 0;
    }

    public function getTotalServicesAttribute(): float
    {
        return $this->services->sum('price') ?? 0;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_parts + $this->total_services;
    }
}
