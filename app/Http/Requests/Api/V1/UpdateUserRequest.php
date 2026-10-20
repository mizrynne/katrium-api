<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\AuthorizesAccessGrants;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use AuthorizesAccessGrants;
    
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var User $target */
        $target = $this->route('user');

        if ($actor->hasRole('Superadmin')) {
            return true;
        }

        if ($target->hasRole('Superadmin')) {
            return false;
        }

        $changed = [];
        if ($this->has('permissions')) {
            $requested = $this->requestedPermissions();
            $current   = $target->permissions->pluck('name')->all();
            $changed   = array_merge(array_diff($requested, $current), array_diff($current, $requested));
        }

        return $this->actorMayGrant($this->input('role'), $changed);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role'  => ['sometimes', 'required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'password'              => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }
}
