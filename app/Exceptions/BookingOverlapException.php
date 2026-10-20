<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;

class BookingOverlapException extends BookingRuleException
{
    public function __construct(string $message, array $errors = [], ?array $conflict = null)
    {
        parent::__construct($message, 'booking_overlap', $errors, $conflict);
    }

    public static function causedBy(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();

        return $sqlState === '23P01'
            || str_contains($e->getMessage(), 'bookings_unit_id_time_overlap_excl');
    }
}