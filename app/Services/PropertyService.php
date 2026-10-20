<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PropertyService
{
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        return Property::query()
            ->visibleTo($actor)
            ->withCount(['units' => fn (Builder $q) => $q->visibleTo($actor)])
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $q) => $q->where('is_active', $filters['is_active'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $q) => $q->where('name', 'ilike', '%' . addcslashes($filters['search'], '%_\\') . '%')
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function show(User $actor, int $id): Property
    {
        return Property::query()
            ->visibleTo($actor)
            ->withCount(['units' => fn (Builder $q) => $q->visibleTo($actor)])
            ->with(['units' => fn ($q) => $q->visibleTo($actor)->orderBy('name')])
            ->findOrFail($id);
    }

    public function create(array $data): Property
    {
        $property = Property::create($data);

        return $property->refresh()->loadCount('units');
    }

    public function update(User $actor, Property $property, array $data): Property
    {
        $property = Property::query()->visibleTo($actor)->findOrFail($property->getKey());

        $property->update($data);

        return $this->show($actor, $property->getKey());
    }

    public function archive(User $actor, Property $property): Property
    {
        $property = Property::query()->visibleTo($actor)->findOrFail($property->getKey());

        if ($property->units()->where('is_active', true)->exists()) {
            throw new ConflictHttpException('Property still has active units. Deactivate or reassign them first.');
        }

        $property->update(['is_active' => false]);

        return $property->loadCount('units');
    }
}