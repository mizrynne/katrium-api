<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBeds24WebhookJob;
use App\Models\WebhookLog;
use App\Services\Beds24SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Beds24WebhookController extends Controller
{
    public function __invoke(Request $request, Beds24SyncService $sync): JsonResponse
    {
        $expected = (string) config('beds24.webhook.secret');
        $provided = (string) $request->header((string) config('beds24.webhook.header'));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'code' => 'invalid_webhook_secret',
                'message' => 'Invalid webhook credentials.',
            ], 401);
        }

        $payload = $request->json()->all();

        if (empty(data_get($payload, 'booking.id'))) {
            return response()->json([
                'success' => false,
                'code' => 'invalid_payload',
                'message' => 'Payload is missing booking.id.',
            ], 422);
        }

        $log = WebhookLog::query()->createOrFirst(
            ['idempotency_key' => $sync->idempotencyKey($payload)],
            [
                'channel' => 'beds24',
                'event_type' => $sync->eventType($payload),
                'payload' => $payload,
                'status' => 'pending',
            ],
        );

        $isDuplicate = ! $log->wasRecentlyCreated;

        if ($isDuplicate) {
            $retryable = $log->status === 'failed'
                || ($log->status === 'pending' && $log->updated_at->lt(now()->subMinutes(5)));

            if (! $retryable) {
                return response()->json([
                    'success' => true,
                    'data' => ['webhook_log_id' => $log->id, 'status' => $log->status, 'duplicate' => true],
                ], 200);
            }

            $log->forceFill(['status' => 'pending', 'error_message' => null])->save();
        }

        ProcessBeds24WebhookJob::dispatch($log->id, (string) data_get($payload, 'booking.id'));

        return response()->json([
            'success' => true,
            'data' => ['webhook_log_id' => $log->id, 'status' => 'queued', 'duplicate' => $isDuplicate],
        ], 202);
    }
}