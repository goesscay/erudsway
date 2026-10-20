<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A user's staff access: the admin switches, custom roles, and permissions given directly. */
class UpdateAccessRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'admin' => ['required', 'boolean'],
            'super_admin' => ['sometimes', 'boolean'],
            // Custom staff roles. Leave out to keep the current ones.
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name'), StoreUserRequest::customRole()],
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ];
    }
}
