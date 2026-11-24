<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pagamento (abatimento) de uma OS. Entra como receita na data paid_at.
 */
class OrderServicePayment extends Model
{
    use SoftDeletes;

    public const METHODS = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'boleto', 'transferencia', 'outro'];

    protected $fillable = [
        'company_id',
        'order_service_id',
        'user_id',
        'amount',
        'paid_at',
        'method',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'date:Y-m-d',
    ];

    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
