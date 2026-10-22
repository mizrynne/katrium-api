<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthService
{
    /** @return array{ok: bool, checks: array<string, bool>} */
    public function check(): array
    {
        $checks = [
            'database' => $this->attempt(fn () => DB::select('select 1')),
            'redis'    => $this->attempt(fn () => Redis::connection()->ping()),
        ];

        return ['ok' => $checks['database'], 'checks' => $checks];
    }

    private function attempt(callable $probe): bool
    {
        try {
            $probe();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}