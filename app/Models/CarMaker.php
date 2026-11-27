<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarMaker extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturer',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Cadastro compartilhado entre oficinas: grava sem espaços sobrando e em
     * maiúsculas (padrão da base), para "Kia", " kia " e "KIA" serem a mesma.
     */
    public static function normalizeName(string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name)), 'UTF-8');
    }

    // Relationships
    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'car_makers_id');
    }
}
