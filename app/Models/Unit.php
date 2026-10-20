<?php

namespace App\Models;

use App\Support\PartnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'parent_id',
        'name',
        'beds24_room_id',
        'qty',
        'capacity',
        'max_adults',
        'max_children',
        'is_active',
        'partner_id',
    ];

    protected function casts(): array
    {
        return [
            'property_id'  => 'integer',
            'parent_id'    => 'integer',
            'partner_id'   => 'integer',
            'qty'          => 'integer',
            'capacity'     => 'integer',
            'max_adults'   => 'integer',
            'max_children' => 'integer',
            'is_active'    => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! PartnerScope::applies($user)) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn('partner_id'), $user->getKey());
    }

    public function hasAncestor(int $ancestorId): bool
    {
        $seen    = [];
        $current = $this;

        while ($current->parent_id !== null && ! in_array($current->parent_id, $seen, true)) {
            if ($current->parent_id === $ancestorId) {
                return true;
            }

            $seen[]  = $current->parent_id;
            $current = self::query()->find($current->parent_id);

            if ($current === null) {
                return false;
            }
        }

        return false;
    }
}