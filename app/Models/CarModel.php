<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'model',
        'car_makers_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function maker(): BelongsTo
    {
        return $this->belongsTo(CarMaker::class, 'car_makers_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'car_models_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return "{$this->maker->manufacturer} {$this->model}";
    }
}
