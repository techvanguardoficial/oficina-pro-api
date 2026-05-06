<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Part extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'description',
        'price',
        'information',
        'quantity',
        'unit_price',
        'orders_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'orders_id');
    }

    // Mutators
    protected static function boot()
    {
        parent::boot();

        // Calcula automaticamente o price baseado em quantity e unit_price
        static::saving(function ($part) {
            if ($part->quantity && $part->unit_price) {
                $part->price = (float)$part->quantity * $part->unit_price;
            }
        });
    }
}
