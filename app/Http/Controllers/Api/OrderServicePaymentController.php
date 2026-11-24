<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Models\OrderService;
use App\Models\OrderServicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pagamentos (abatimentos) de uma OS: o cliente pode pagar em partes,
 * e a OS mostra quanto já foi pago e quanto falta.
 */
class OrderServicePaymentController extends Controller
{
    use AuthorizesCompany;

    /** GET /order-services/{id}/payments */
    public function index(OrderService $orderService)
    {
        $this->authorizeCompany($orderService);

        return response()->json($this->summary($orderService));
    }

    /**
     * POST /order-services/{id}/payments
     * { amount, paid_at?, method?, notes?, payment_due_date? }
     * payment_due_date: nova data combinada para o restante (some quando a OS é quitada).
     */
    public function store(Request $request, OrderService $orderService)
    {
        $this->authorizeCompany($orderService);

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01|max:99999999.99',
            'paid_at'          => 'nullable|date|before_or_equal:today',
            'method'           => ['nullable', 'string', Rule::in(OrderServicePayment::METHODS)],
            'notes'            => 'nullable|string|max:500',
            'payment_due_date' => 'nullable|date',
        ]);

        $payment = DB::transaction(function () use ($validated, $orderService, $request) {
            // Trava a OS: dois pagamentos ao mesmo tempo não podem passar do total
            $order = OrderService::whereKey($orderService->id)->lockForUpdate()->firstOrFail();

            if (in_array((int) $order->orders_status_id, OrderService::canceledStatusIds(), true)) {
                throw ValidationException::withMessages([
                    'amount' => 'OS cancelada não recebe pagamento.',
                ]);
            }

            $amount = round((float) $validated['amount'], 2);
            if ($amount > $order->remaining + OrderService::PAYMENT_EPSILON) {
                throw ValidationException::withMessages([
                    'amount' => $order->remaining > 0
                        ? 'O valor passa do restante da OS (R$ ' . number_format($order->remaining, 2, ',', '.') . ').'
                        : 'Esta OS não tem valor a receber.',
                ]);
            }

            $payment = OrderServicePayment::create([
                'company_id'       => $order->company_id,
                'order_service_id' => $order->id,
                'user_id'          => $request->user()->id,
                'amount'           => $amount,
                'paid_at'          => $validated['paid_at'] ?? now()->toDateString(),
                'method'           => $validated['method'] ?? null,
                'notes'            => $validated['notes'] ?? null,
            ]);

            $order->unsetRelation('payments');
            if ($order->remaining <= 0) {
                $order->payment_due_date = null;
            } elseif (array_key_exists('payment_due_date', $validated)) {
                $order->payment_due_date = $validated['payment_due_date'];
            }
            $order->save();

            return $payment;
        });

        return response()->json([
            'message' => 'Pagamento registrado',
            'payment' => $payment,
            ...$this->summary($orderService->refresh()),
        ], 201);
    }

    /** DELETE /order-services/{id}/payments/{payment}: estorna um lançamento. */
    public function destroy(OrderService $orderService, OrderServicePayment $payment)
    {
        $this->authorizeCompany($orderService);

        $payment->delete();

        return response()->json([
            'message' => 'Pagamento removido',
            ...$this->summary($orderService->refresh()),
        ]);
    }

    private function summary(OrderService $order): array
    {
        $order->load(['parts', 'services', 'payments.user:id,name']);

        return [
            'order_service_id' => $order->id,
            'total'            => $order->total,
            'paid_total'       => $order->paid_total,
            'remaining'        => $order->remaining,
            'payment_status'   => $order->payment_status,
            'payment_due_date' => $order->payment_due_date?->toDateString(),
            'payments'         => $order->payments,
        ];
    }
}
