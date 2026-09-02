<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ClientAppUser extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cpf',
        'phone',
        'device_token',
        'email_verified_at',
        'onboarding_completed',
        'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'last_login_at'          => 'datetime',
        'email_verified_at'      => 'datetime',
        'onboarding_completed'   => 'boolean',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_app_user_clients')
            ->withoutGlobalScope(CompanyScope::class)
            ->withTimestamps();
    }

    public function clientIds(): array
    {
        return $this->clients()->pluck('clients.id')->toArray();
    }
}
