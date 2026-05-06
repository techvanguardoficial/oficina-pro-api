<?php

namespace App\Exceptions;

class ResourceNotFoundException extends ApiException
{
    public function __construct(
        string $resource = 'Resource',
        string $identifier = ''
    ) {
        $message = "{$resource} not found";
        if ($identifier) {
            $message .= " (ID: {$identifier})";
        }

        parent::__construct(
            $message,
            'RESOURCE_NOT_FOUND',
            404
        );
    }
}
