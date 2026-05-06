<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\CompanyScope;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'placa';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    protected $fillable = [
        'placa',
        'info',
        'chassis',
        'year',
        'color',
        'km',
        'company_id',
        'car_models_id',
        'clients_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clients_id');
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_models_id');
    }

    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class, 'vehicle_placa', 'placa');
    }

    public function mileages(): HasMany
    {
        return $this->hasMany(CarMileage::class, 'vehicles_placa', 'placa');
    }

    protected $appends = ['current_km', 'formatted_placa'];

    // Accessors
    public function getCurrentKmAttribute(): string|null
    {
        $lastMileage = $this->mileages()->orderByDesc('created_at')->value('mileage');
        return $lastMileage ?? $this->km;
    }

    public function getFormattedPlacaAttribute(): string
    {
        if (strlen($this->placa) === 7) {
            return strtoupper(substr($this->placa, 0, 3) . '-' . substr($this->placa, 3));
        }
        return strtoupper($this->placa);
    }
}
