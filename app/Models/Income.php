<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\CompanyScope;

class Income extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    protected $fillable = [
        'company_id',
        'income_types_id',
        'info',
        'value',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'deleted_at' => 'datetime',
        'value' => 'float',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_types_id');
    }
}
