<?php

namespace App\Exceptions;

use Stripe\Exception\ApiErrorException;

class StripeException extends ApiException
{
    public function __construct(
        string $message = 'Stripe payment error',
        ?ApiErrorException $previous = null
    ) {
        $statusCode = 500;
        $details = [];

        if ($previous) {
            $statusCode = $previous->getHttpStatus() ?? 500;
            $details = [
                'stripe_error_code' => $previous->getStripeCode(),
                'stripe_error_type' => $previous->getStripeType(),
            ];
        }

        parent::__construct(
            $message,
            'STRIPE_ERROR',
            $statusCode,
            $details,
            $previous
        );
    }
}
