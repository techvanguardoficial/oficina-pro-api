<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected array $details = [];

    public function __construct(
        string $message,
        string $errorCode = 'INTERNAL_ERROR',
        int $statusCode = 500,
        array $details = [],
        Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => $this->message,
            'code' => $this->errorCode,
            'details' => $this->details,
        ], $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
