<?php

namespace App\Models;

use App\Support\PartnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'beds24_id',
        'name',
        'currency',
        'timezone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'latitude',
        'longitude',
        'check_in_time',
        'check_out_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! PartnerScope::applies($user)) {
            return $query;
        }

        return $query->whereHas(
            'units',
            fn (Builder $q) => $q->where('partner_id', $user->getKey())
        );
    }
}