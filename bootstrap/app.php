<?php

use Throwable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client.app' => \App\Http\Middleware\EnsureClientAppUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e) {
            // Handle custom API exceptions
            if ($e instanceof \App\Exceptions\ApiException) {
                return $e->render();
            }

            // Handle Stripe exceptions
            if ($e instanceof \Stripe\Exception\ApiErrorException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Payment processing failed',
                    'code' => 'STRIPE_ERROR',
                    'details' => [
                        'stripe_error_code' => $e->getStripeCode(),
                        'stripe_error_type' => $e->getStripeType(),
                    ],
                ], $e->getHttpStatus() ?? 500);
            }

            // Handle Laravel validation exceptions
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'details' => $e->errors(),
                ], 422);
            }

            // Handle model not found exceptions
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Resource not found',
                    'code' => 'RESOURCE_NOT_FOUND',
                    'details' => [],
                ], 404);
            }

            // Handle authentication exceptions
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthenticated',
                    'code' => 'UNAUTHENTICATED',
                    'details' => [],
                ], 401);
            }

            // Handle authorization exceptions
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'error' => true,
                    'message' => 'Forbidden',
                    'code' => 'FORBIDDEN',
                    'details' => [],
                ], 403);
            }

            // Handle HTTP exceptions
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'code' => 'HTTP_ERROR',
                    'details' => [],
                ], $e->getStatusCode());
            }

            // Generic error handler - don't expose stack trace in production
            $code = (int) $e->getCode();
            $statusCode = $code >= 400 && $code < 600 ? $code : 500;

            return response()->json([
                'error' => true,
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                'code' => 'INTERNAL_ERROR',
                'details' => config('app.debug') ? [
                    'exception' => class_basename($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : [],
            ], $statusCode);
        });

        // Log exceptions
        $exceptions->reportable(function (Throwable $e) {
            if (!$e instanceof \App\Exceptions\ApiException) {
                \Log::error('Exception occurred', [
                    'exception' => class_basename($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    })->create();
