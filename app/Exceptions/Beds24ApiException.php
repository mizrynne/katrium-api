<?php

namespace App\Exceptions;

use RuntimeException;

class Beds24ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?int $status = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function permanent(string $message, array $errors = [], ?int $status = null): self
    {
        return new self($message, false, $status, $errors);
    }
}