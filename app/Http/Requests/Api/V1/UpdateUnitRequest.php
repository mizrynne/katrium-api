<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Unit;
use App\Rules\IsBusinessPartner;

class UpdateUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage:units') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');
 
        return [
            'property_id'    => ['prohibited'],
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id'      => [
                'sometimes', 'nullable', 'integer',
                Rule::notIn([$unit->getKey()]),
                Rule::exists('units', 'id')->where('property_id', $unit->property_id),
            ],
            'beds24_room_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'qty'            => ['sometimes', 'integer', 'min:1'],
            'capacity'       => ['sometimes', 'integer', 'min:1'],
            'max_adults'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_children'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'partner_id'     => ['sometimes', 'nullable', 'integer', new IsBusinessPartner()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parentId = $this->input('parent_id');
 
                if (! $parentId || $validator->errors()->has('parent_id')) {
                    return;
                }
 
                /** @var Unit $unit */
                $unit   = $this->route('unit');
                $parent = Unit::query()->find($parentId);
 
                if ($parent && $parent->hasAncestor($unit->getKey())) {
                    $validator->errors()->add('parent_id', 'A unit cannot be assigned a parent that is its own descendant.');
                }
            },
        ];
    }
}
