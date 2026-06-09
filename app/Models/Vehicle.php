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

    // A partir da migração 2026_06_15_*, `id` (autoincrement) é a PK e
    // `placa` deixou de ser identificador global único — agora é única por
    // empresa (unique composto company_id+placa), permitindo que o mesmo
    // veículo físico seja atendido em oficinas diferentes sem conflito.

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    /**
     * As rotas/URLs do app continuam usando a placa como identificador
     * "amigável" (ex.: /clients/64/vehicles/GSV5470). Como `placa` deixou de
     * ser PK/única globalmente — agora é única por empresa — fazemos o
     * binding manualmente escopado por company_id (via CompanyScope, que já
     * está ativo aqui) para resolver corretamente o veículo desta oficina.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'placa', $value)->firstOrFail();
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
        return $this->hasMany(OrderService::class, 'vehicle_id', 'id');
    }

    public function mileages(): HasMany
    {
        return $this->hasMany(CarMileage::class, 'vehicle_id', 'id');
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
