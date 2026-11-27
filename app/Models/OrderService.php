<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Company;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'info',
        'vehicle_id',
        'orders_types_id',
        'orders_status_id',
        'payment_due_date',
        'completed_at',
    ];

    /** Saldo de pagamento sempre vem junto da OS. */
    protected $appends = ['total', 'paid_total', 'remaining', 'payment_status'];

    /** Diferença abaixo de meio centavo conta como zero (arredondamento). */
    public const PAYMENT_EPSILON = 0.005;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime',
        'payment_due_date' => 'date:Y-m-d',
    ];

    /**
     * IDs de orders_status que significam "concluída".
     * once(): uma consulta por requisição; o Laravel limpa esse cache entre testes e entre requisições no Octane.
     */
    public static function completedStatusIds(): array
    {
        return once(fn () => OrderStatus::whereIn('status', ['Concluído', 'CONCLUIDO'])->pluck('id')->all());
    }

    /** IDs de orders_status que significam "cancelada" (a base de produção usa CANCELADA). */
    public static function canceledStatusIds(): array
    {
        return once(fn () => OrderStatus::whereIn('status', ['Cancelado', 'CANCELADO', 'Cancelada', 'CANCELADA'])
            ->pluck('id')
            ->all());
    }

    /** IDs de orders_status que encerram a OS (concluída ou cancelada). O resto conta como "em aberto". */
    public static function closedStatusIds(): array
    {
        return array_values(array_unique([...self::completedStatusIds(), ...self::canceledStatusIds()]));
    }

    /**
     * Data de conclusão informada pelo usuário (Y-m-d, no fuso dele).
     * Grava ao meio-dia UTC para o dia não virar em nenhum fuso do Brasil.
     */
    public static function completedAtFromDate(string $ymd): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $ymd, 'UTC')->setTime(12, 0);
    }

    protected static function booted(): void
    {
        // Registra a data de conclusão ao entrar no status concluído
        // e limpa se a OS for reaberta.
        static::saving(function (OrderService $order) {
            if (!$order->isDirty('orders_status_id') && $order->exists) return;

            $isCompleted = in_array((int) $order->orders_status_id, self::completedStatusIds(), true);
            if ($isCompleted && !$order->completed_at) {
                $order->completed_at = now();
            } elseif (!$isCompleted) {
                $order->completed_at = null;
            }
        });
    }

    // Relationships
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrderType::class, 'orders_types_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'orders_status_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'orders_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'orders_id');
    }

    public function mileages(): HasMany
    {
        return $this->hasMany(CarMileage::class, 'order_services_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderServicePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Accessors
    public function getTotalPartsAttribute(): float
    {
        return $this->parts->sum(fn($p) => ($p->unit_price ?? $p->price ?? 0) * max(1, (float)($p->quantity ?? 1)));
    }

    public function getTotalServicesAttribute(): float
    {
        return $this->services->sum(fn($s) => $s->price ?? 0);
    }

    public function getTotalAttribute(): float
    {
        return round($this->total_parts + $this->total_services, 2);
    }

    public function getPaidTotalAttribute(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    /** Quanto falta pagar. Nunca negativo. */
    public function getRemainingAttribute(): float
    {
        $remaining = round($this->total - $this->paid_total, 2);
        return $remaining > self::PAYMENT_EPSILON ? $remaining : 0.0;
    }

    /** sem_valor (OS sem itens), pendente (nada pago), parcial ou pago. */
    public function getPaymentStatusAttribute(): string
    {
        if ($this->remaining > 0) {
            return $this->paid_total > 0 ? 'parcial' : 'pendente';
        }
        return $this->total > 0 || $this->paid_total > 0 ? 'pago' : 'sem_valor';
    }

    // Saldo no banco (para listar e somar sem carregar cada OS)

    /** Total da OS em SQL, mesma regra de getTotalAttribute(). */
    public static function totalSql(string $table = 'order_services'): string
    {
        return "(COALESCE((SELECT SUM(COALESCE(p.unit_price, p.price, 0) * GREATEST(1, CAST(COALESCE(NULLIF(p.quantity, ''), '1') AS DECIMAL(12,3))))"
            . " FROM parts p WHERE p.orders_id = {$table}.id AND p.deleted_at IS NULL), 0)"
            . " + COALESCE((SELECT SUM(COALESCE(s.price, 0)) FROM services s WHERE s.orders_id = {$table}.id AND s.deleted_at IS NULL), 0))";
    }

    /** Soma dos pagamentos da OS em SQL. */
    public static function paidSql(string $table = 'order_services'): string
    {
        return "COALESCE((SELECT SUM(op.amount) FROM order_service_payments op"
            . " WHERE op.order_service_id = {$table}.id AND op.deleted_at IS NULL), 0)";
    }

    /** Saldo (total - pago) em SQL. */
    public static function remainingSql(string $table = 'order_services'): string
    {
        return '(' . self::totalSql($table) . ' - ' . self::paidSql($table) . ')';
    }

    /**
     * OS com pagamento pendente: não cancelada, concluída ou com algum pagamento (sinal),
     * e com saldo a receber.
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query
            ->whereNotIn('order_services.orders_status_id', self::canceledStatusIds())
            ->where(fn (Builder $q) => $q
                ->whereIn('order_services.orders_status_id', self::completedStatusIds())
                ->orWhereExists(fn ($e) => $e->selectRaw('1')
                    ->from('order_service_payments as opx')
                    ->whereColumn('opx.order_service_id', 'order_services.id')
                    ->whereNull('opx.deleted_at')))
            ->whereRaw(self::remainingSql() . ' > ?', [self::PAYMENT_EPSILON]);
    }

    /**
     * Resumo do que a oficina tem a receber: quantidade de OS, valor a receber
     * e quanto disso já venceu (payment_due_date anterior a hoje).
     */
    public static function pendingPaymentSummary(int $companyId): array
    {
        $remaining = self::remainingSql();
        $today = now()->toDateString();

        $row = self::query()
            ->where('order_services.company_id', $companyId)
            ->pendingPayment()
            ->toBase()
            ->selectRaw(
                "COUNT(*) AS orders_count,
                 COALESCE(SUM({$remaining}), 0) AS receivable,
                 COALESCE(SUM(CASE WHEN order_services.payment_due_date < ? THEN 1 ELSE 0 END), 0) AS overdue_count,
                 COALESCE(SUM(CASE WHEN order_services.payment_due_date < ? THEN {$remaining} ELSE 0 END), 0) AS overdue",
                [$today, $today]
            )
            ->first();

        return [
            'orders'        => (int) $row->orders_count,
            'receivable'    => round((float) $row->receivable, 2),
            'overdueOrders' => (int) $row->overdue_count,
            'overdue'       => round((float) $row->overdue, 2),
        ];
    }
}
