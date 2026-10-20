<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

/** Create or edit a custom staff role. */
class SaveRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->input('name')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var RoleModel|null $current */
        $current = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:50',
                function (string $attribute, mixed $value, Closure $fail) use ($current) {
                    $name = mb_strtolower((string) $value);
                    if (Role::isBuiltIn($name)) {
                        $fail('That name belongs to a built-in role. Choose another.');

                        return;
                    }
                    $taken = RoleModel::query()->whereRaw('lower(name) = ?', [$name])
                        ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))->exists();
                    if ($taken) {
                        $fail('A role with this name already exists.');
                    }
                },
            ],
            'description' => ['present', 'nullable', 'string', 'max:300'],
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ];
    }
}
