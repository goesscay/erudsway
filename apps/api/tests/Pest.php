<?php

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Support/authoring.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** Headers that make Sanctum treat the request as coming from the web app (session + cookies). */
function spaHeaders(): array
{
    return ['Referer' => 'http://localhost:3000/', 'Origin' => 'http://localhost:3000', 'Accept' => 'application/json'];
}

function makeUser(Role $role = Role::Student, bool $verified = true): User
{
    $factory = User::factory()->withRole($role);

    return ($verified ? $factory : $factory->unverified())->create();
}

/** A staff member holding only the given permissions (no admin role). */
function makeStaff(Permission ...$permissions): User
{
    $user = makeUser();
    $user->givePermissionTo(array_map(fn (Permission $p) => $p->value, $permissions));

    return $user;
}
