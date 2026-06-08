<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class ClientAppUser extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'phone',
        'device_token',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    // Retorna todos os registros de cliente em todas as oficinas
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_app_user_clients')
            ->withoutGlobalScope(CompanyScope::class)
            ->withTimestamps();
    }

    // IDs de todos os clients vinculados (usado nas queries de veículos/ordens)
    public function clientIds(): array
    {
        return $this->clients()->pluck('clients.id')->toArray();
    }
}
