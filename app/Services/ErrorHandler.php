<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorHandler
{
    /**
     * Log an error with structured context
     */
    public static function logError(
        Throwable $exception,
        string $context = 'API Error',
        array $additionalData = []
    ): void {
        Log::error($context, [
            'exception' => class_basename($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
            ...$additionalData,
        ]);
    }

    /**
     * Format error response
     */
    public static function formatError(
        string $message,
        string $code = 'ERROR',
        int $statusCode = 500,
        array $details = []
    ): array {
        return [
            'error' => true,
            'message' => $message,
            'code' => $code,
            'details' => $details,
        ];
    }

    /**
     * Handle database/model exceptions
     */
    public static function handleDatabaseError(Throwable $e): string
    {
        if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return 'Duplicate entry found';
        }

        if (str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')) {
            return 'Invalid reference to related record';
        }

        if (config('app.debug')) {
            return $e->getMessage();
        }

        return 'Database error occurred';
    }

    /**
     * Handle Stripe errors
     */
    public static function handleStripeError(Throwable $e): array
    {
        $message = 'Payment processing failed';
        $details = [];

        if (method_exists($e, 'getStripeCode')) {
            $details['stripe_code'] = $e->getStripeCode();
        }

        if (method_exists($e, 'getStripeType')) {
            $details['stripe_type'] = $e->getStripeType();
        }

        // Friendly messages for common Stripe errors
        if (str_contains($e->getMessage(), 'card_declined')) {
            $message = 'Your card was declined';
        } elseif (str_contains($e->getMessage(), 'expired_card')) {
            $message = 'Your card has expired';
        } elseif (str_contains($e->getMessage(), 'incorrect_cvc')) {
            $message = 'Incorrect CVC';
        } elseif (str_contains($e->getMessage(), 'processing_error')) {
            $message = 'An error occurred while processing your payment';
        }

        return [
            'message' => $message,
            'details' => $details,
        ];
    }
}
