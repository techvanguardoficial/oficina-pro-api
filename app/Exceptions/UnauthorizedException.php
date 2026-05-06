<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(
        string $message = 'Unauthorized access'
    ) {
        parent::__construct(
            $message,
            'UNAUTHORIZED',
            401
        );
    }
}
