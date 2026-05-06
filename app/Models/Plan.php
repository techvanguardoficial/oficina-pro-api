<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'interval',
        'stripe_product_id',
        'stripe_price_id',
        'trial_days',
        'max_users',
        'max_vehicles',
        'max_orders',
        'max_clients',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_vehicles' => 'integer',
        'max_orders' => 'integer',
        'max_clients' => 'integer',
        'is_active' => 'boolean',
    ];

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('is_included')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(Feature|string $feature): bool
    {
        if (is_string($feature)) {
            return $this->features()
                ->where('slug', $feature)
                ->where('is_included', true)
                ->exists();
        }

        return $this->features()
            ->where('feature_id', $feature->id)
            ->where('is_included', true)
            ->exists();
    }
}
