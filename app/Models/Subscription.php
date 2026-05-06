<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'plan_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'status',
        'trial_starts_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ended_at',
        'cancel_reason',
    ];

    protected $casts = [
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trialing' &&
               $this->trial_ends_at &&
               $this->trial_ends_at->isFuture();
    }

    public function trialEndsIn(): ?int
    {
        if (!$this->isOnTrial()) {
            return null;
        }

        return now()->diffInDays($this->trial_ends_at);
    }

    public function canRenew(): bool
    {
        return $this->current_period_end &&
               now()->lessThan($this->current_period_end);
    }

    public function hasFeature(Feature|string $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }

    public function checkLimits(): array
    {
        $company = $this->company;
        $plan = $this->plan;
        $violations = [];

        if ($plan->max_users && $company->users()->count() > $plan->max_users) {
            $violations['users'] = [
                'current' => $company->users()->count(),
                'limit' => $plan->max_users,
            ];
        }

        if ($plan->max_clients && $company->clients()->count() > $plan->max_clients) {
            $violations['clients'] = [
                'current' => $company->clients()->count(),
                'limit' => $plan->max_clients,
            ];
        }

        if ($plan->max_vehicles && $company->vehicles()->count() > $plan->max_vehicles) {
            $violations['vehicles'] = [
                'current' => $company->vehicles()->count(),
                'limit' => $plan->max_vehicles,
            ];
        }

        if ($plan->max_orders && $company->orderServices()->count() > $plan->max_orders) {
            $violations['orders'] = [
                'current' => $company->orderServices()->count(),
                'limit' => $plan->max_orders,
            ];
        }

        return $violations;
    }
}
