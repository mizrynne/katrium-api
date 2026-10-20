<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GuestTag;
use App\Models\Guest;
use App\Models\GuestViolation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GuestViolationService
{
    public function paginateFor(Guest $guest, int $perPage = 25): LengthAwarePaginator
    {
        return $guest->violations()
            ->with(['unit:id,name', 'recordedBy:id,name'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function record(Guest $guest, User $recordedBy, array $data): GuestViolation
    {
        return DB::transaction(function () use ($guest, $recordedBy, $data): GuestViolation {
            $violation = $guest->violations()->create([
                'unit_id' => $data['unit_id'] ?? null,
                'recorded_by_user_id' => $recordedBy->getKey(),
                'severity' => $data['severity'],
                'reason' => trim((string) $data['reason']),
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
            ]);

            if (filter_var($data['blacklist_guest'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $guest->forceFill(['tag' => GuestTag::Blacklisted])->save();
            }

            return $violation->load(['unit:id,name', 'recordedBy:id,name']);
        });
    }
}
