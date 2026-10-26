<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * @unauthenticated
     */
    public function __invoke(HealthService $health): JsonResponse
    {
        $result = $health->check();

        return response()->json([
            'success'   => $result['ok'],
            'status'    => $result['ok'] ? 'ok' : 'degraded',
            'checks'    => $result['checks'],
            'timestamp' => now()->toIso8601String(),
        ], $result['ok'] ? 200 : 503);
    }
}