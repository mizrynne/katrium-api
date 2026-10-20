<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuestTag;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'country',
        'tag',
        'total_stays',
        'notes',
    ];

    /** Mirrors the column defaults on the guests table so fresh instances serialize correctly. */
    protected $attributes = [
        'tag' => 'regular',
        'total_stays' => 1,
    ];

    protected function casts(): array
    {
        return [
            'tag' => GuestTag::class,
            'total_stays' => 'integer',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(function (): ?string {
            $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

            return $name === '' ? null : $name;
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(GuestViolation::class);
    }
}
