<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\PhoneController;
use App\Http\Controllers\Api\CarMakerController;
use App\Http\Controllers\Api\CarModelController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\OrderTypeController;
use App\Http\Controllers\Api\OrderStatusController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\OrderServiceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\PartController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\AbacatePayWebhookController;

Route::prefix('v1')->group(function () {

    Route::get('status', function () {
        return response()->json(['status' => 'API V1 Servcar is alive!'], 200);
    });

    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::get('reset-password/{token}', function (Request $request, $token) {
        $email = $request->query('email');
        return redirect("http://localhost/?token={$token}&email={$email}");
    })->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    // Planos - Público
    Route::get('plans', [SubscriptionController::class, 'listPlans']);
    Route::get('plans/{plan}', [SubscriptionController::class, 'getPlan']);

    // Webhooks - Sem autenticação
    Route::post('webhook/stripe', [PaymentController::class, 'webhook']);
    Route::post('webhook/abacatepay', [AbacatePayWebhookController::class, 'handle']);

    // Subscription cancel (public route, no auth required)
    Route::get('subscription/checkout-canceled', [SubscriptionController::class, 'checkoutCanceled'])->name('subscription.cancel');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Subscriptions - Protegidas
        Route::get('subscription', [SubscriptionController::class, 'getCurrentSubscription']);
        Route::post('subscription', [SubscriptionController::class, 'updateSubscription']);
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::delete('subscription', [SubscriptionController::class, 'cancelSubscription']);
        Route::get('subscription/invoices', [SubscriptionController::class, 'invoices']);
        Route::post('subscription/confirm', [SubscriptionController::class, 'confirm']);
        Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('subscription/resume', [SubscriptionController::class, 'resume']);
        Route::get('subscription/status', [SubscriptionController::class, 'status']);

        // Payments - Protegidas
        Route::post('payment-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('subscription/checkout-pix', [PaymentController::class, 'checkoutPix']);
        Route::get('payment-methods', [PaymentController::class, 'listPaymentMethods']);
        Route::post('payment-methods/{id}/delete', [PaymentController::class, 'deletePaymentMethod']);
        Route::post('payment-methods/{id}/set-default', [PaymentController::class, 'setDefaultPaymentMethod']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('clients', ClientController::class);
        Route::get('clients/{client}/vehicles', [ClientController::class, 'getVehicles']);
        Route::apiResource('addresses', AddressController::class);
        Route::apiResource('phones', PhoneController::class);
        Route::apiResource('car-makers', CarMakerController::class);
        Route::apiResource('car-models', CarModelController::class);
        Route::get('car-makers/{makerId}/models', [CarModelController::class, 'getByMaker']);
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('order-services/list-all', [OrderServiceController::class, 'listAll']);
        Route::apiResource('order-services', OrderServiceController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('parts', PartController::class);
        Route::apiResource('incomes', IncomeController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('order-types', OrderTypeController::class);
        Route::apiResource('order-statuses', OrderStatusController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('stocks', StockController::class);
        Route::get('transactions', [TransactionController::class, 'summary']);
    });

});
