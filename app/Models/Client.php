<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\CompanyScope;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    protected $fillable = [
        'company_id',
        'name',
        'lastname',
        'email',
        'cpf_cnpj',
        'status',
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

    public function address(): HasOne
    {
        return $this->hasOne(Address::class, 'clients_id');
    }

    public function phone(): HasOne
    {
        return $this->hasOne(Phone::class, 'clients_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'clients_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->lastname}");
    }

    public function isActive(): bool
    {
        return $this->status === '1';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
}
