<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeType extends Model
{
    use SoftDeletes;

    protected $fillable = ['type'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'income_types_id');
    }
}
