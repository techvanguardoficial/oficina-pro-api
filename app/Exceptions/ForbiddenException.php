<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(
        string $message = 'Forbidden access'
    ) {
        parent::__construct(
            $message,
            'FORBIDDEN',
            403
        );
    }
}
