<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class BookingRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'booking_rule_violation',
        public readonly array $errors = [],
        public readonly ?array $conflict = null,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'errors' => (object) $this->errors,
            'conflict' => $this->conflict,
        ], 422);
    }

    public function report(): bool
    {
        return true;
    }
}