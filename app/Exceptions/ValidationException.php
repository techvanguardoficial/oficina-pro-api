<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(
        string $message = 'Validation failed',
        array $details = []
    ) {
        parent::__construct(
            $message,
            'VALIDATION_ERROR',
            422,
            $details
        );
    }
}
