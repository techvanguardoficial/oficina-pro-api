<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Company;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'info',
        'vehicle_id',
        'orders_types_id',
        'orders_status_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** IDs de orders_status que significam "concluída". */
    public static function completedStatusIds(): array
    {
        static $ids = null;
        return $ids ??= OrderStatus::whereIn('status', ['Concluído', 'CONCLUIDO'])->pluck('id')->all();
    }

    protected static function booted(): void
    {
        // Registra a data de conclusão ao entrar no status concluído
        // e limpa se a OS for reaberta.
        static::saving(function (OrderService $order) {
            if (!$order->isDirty('orders_status_id') && $order->exists) return;

            $isCompleted = in_array((int) $order->orders_status_id, self::completedStatusIds(), true);
            if ($isCompleted && !$order->completed_at) {
                $order->completed_at = now();
            } elseif (!$isCompleted) {
                $order->completed_at = null;
            }
        });
    }

    // Relationships
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Accessors
    public function getTotalPartsAttribute(): float
    {
        return $this->parts->sum(fn($p) => ($p->unit_price ?? $p->price ?? 0) * max(1, (float)($p->quantity ?? 1)));
    }

    public function getTotalServicesAttribute(): float
    {
        return $this->services->sum(fn($s) => $s->price ?? 0);
    }

    public function getTotalAttribute(): float
    {
        return $this->total_parts + $this->total_services;
    }
}
