<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\CompanyScope;

class Expense extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    protected $fillable = [
        'company_id',
        'expense_types_id',
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
        return $this->belongsTo(ExpenseType::class, 'expense_types_id');
    }
}
