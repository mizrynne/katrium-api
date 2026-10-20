<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use App\Support\PartnerScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UnitService
{
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $scoped = PartnerScope::applies($actor);

        return Unit::query()
            ->visibleTo($actor)
            ->with(['property', 'partner'])
            ->when(
                isset($filters['property_id']),
                fn (Builder $q) => $q->where('property_id', $filters['property_id'])
            )
            ->when(
                ! $scoped && isset($filters['partner_id']),
                fn (Builder $q) => $q->where('partner_id', $filters['partner_id'])
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $q) => $q->where('is_active', $filters['is_active'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $q) => $q->where('name', 'ilike', '%' . addcslashes($filters['search'], '%_\\') . '%')
            )
            ->orderBy('property_id')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function show(User $actor, int $id): Unit
    {
        return Unit::query()
            ->visibleTo($actor)
            ->with([
                'property',
                'partner',
                'parent'   => fn ($q) => $q->visibleTo($actor),
                'children' => fn ($q) => $q->visibleTo($actor)->orderBy('name'),
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Unit
    {
        $unit = Unit::create($data);

        return $unit->refresh()->load(['property', 'partner']);
    }

    public function update(User $actor, Unit $unit, array $data): Unit
    {
        $unit = Unit::query()->visibleTo($actor)->findOrFail($unit->getKey());

        $unit->update($data);

        return $unit->load(['property', 'partner']);
    }

    public function archive(User $actor, Unit $unit): Unit
    {
        $unit = Unit::query()->visibleTo($actor)->findOrFail($unit->getKey());

        $unit->update(['is_active' => false]);

        return $unit->load(['property', 'partner']);
    }
}