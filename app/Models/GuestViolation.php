<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ViolationSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestViolation extends Model
{
    protected $fillable = [
        'guest_id',
        'unit_id',
        'recorded_by_user_id',
        'severity',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'severity' => ViolationSeverity::class,
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
