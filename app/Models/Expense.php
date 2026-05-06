<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'description',
        'value',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'deleted_at' => 'datetime',
    ];
}
