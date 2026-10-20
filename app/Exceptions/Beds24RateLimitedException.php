<?php

namespace App\Exceptions;

class Beds24RateLimitedException extends Beds24ApiException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Beds24 credit limit reached; retry in {$retryAfter}s.", true, 429);
    }
}