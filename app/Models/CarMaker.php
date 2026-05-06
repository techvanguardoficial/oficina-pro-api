<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarMaker extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturer',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'car_makers_id');
    }
}
