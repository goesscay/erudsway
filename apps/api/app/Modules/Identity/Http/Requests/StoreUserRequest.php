<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff create an account; the person sets their own password from an emailed link. */
class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            // Custom staff roles to give straight away (admins only).
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name'), self::customRole()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.unique' => 'An account with this email already exists.'];
    }

    /** Rejects built-in roles, which are given another way (sign-up, applications, the admin switch). */
    public static function customRole(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (Role::tryFrom((string) $value) !== null) {
                $fail('Built-in roles can’t be given here.');
            }
        };
    }
}
