<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'max_parts_for_order',
        'max_services_for_order',
        'max_clients',
        'max_stock_quantity',
        'has_advanced_reports',
        'has_api_access',
        'has_priority_support',
        'has_multi_branch',
        'has_stock_management',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_vehicles' => 'integer',
        'max_orders' => 'integer',
        'max_parts_for_order' => 'integer',
        'max_services_for_order' => 'integer',
        'max_clients' => 'integer',
        'max_stock_quantity' => 'integer',
        'has_advanced_reports' => 'boolean',
        'has_api_access' => 'boolean',
        'has_priority_support' => 'boolean',
        'has_multi_branch' => 'boolean',
        'has_stock_management' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
