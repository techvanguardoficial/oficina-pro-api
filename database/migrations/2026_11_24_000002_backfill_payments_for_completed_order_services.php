<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A receita das OS passou a contar pela data de cada pagamento, e não mais
 * pelo total na data de conclusão. Para o histórico não mudar, toda OS já
 * concluída recebe um pagamento integral (o total dela) na data de conclusão.
 *
 * O total segue a regra de OrderService::total:
 * peças = (unit_price ?? price ?? 0) × max(1, quantidade); serviços = price.
 */
return new class extends Migration
{
    public const NOTE = 'Pagamento integral (registro automático do histórico)';

    public function up(): void
    {
        $completedIds = DB::table('orders_status')
            ->whereIn('status', ['Concluído', 'CONCLUIDO'])
            ->pluck('id');

        if ($completedIds->isEmpty()) {
            return;
        }

        $total = "COALESCE((SELECT SUM(COALESCE(p.unit_price, p.price, 0) * GREATEST(1, CAST(COALESCE(NULLIF(p.quantity, ''), '1') AS DECIMAL(12,3))))
                    FROM parts p WHERE p.orders_id = os.id AND p.deleted_at IS NULL), 0)
               + COALESCE((SELECT SUM(COALESCE(s.price, 0))
                    FROM services s WHERE s.orders_id = os.id AND s.deleted_at IS NULL), 0)";

        $placeholders = implode(',', array_fill(0, $completedIds->count(), '?'));

        DB::insert(
            "INSERT INTO order_service_payments (company_id, order_service_id, amount, paid_at, notes, created_at, updated_at)
             SELECT t.company_id, t.id, t.total, t.paid_at, ?, NOW(), NOW()
             FROM (
                 SELECT os.id, os.company_id, ROUND({$total}, 2) AS total,
                        DATE(COALESCE(os.completed_at, os.updated_at)) AS paid_at
                 FROM order_services os
                 WHERE os.deleted_at IS NULL
                   AND os.orders_status_id IN ({$placeholders})
                   AND NOT EXISTS (SELECT 1 FROM order_service_payments op WHERE op.order_service_id = os.id)
             ) t
             WHERE t.total > 0",
            [self::NOTE, ...$completedIds->all()]
        );
    }

    public function down(): void
    {
        DB::table('order_service_payments')->where('notes', self::NOTE)->delete();
    }
};
