<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;

beforeEach(function () {
    $this->gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST', 'position' => 0]);
    $this->tax = Category::query()->create(['slug' => 'income-tax', 'name' => 'Income Tax', 'position' => 1]);
    $this->returns = Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $this->gst->id, 'position' => 0]);
    $this->itc = Category::query()->create(['slug' => 'input-tax-credit', 'name' => 'Input Tax Credit', 'parent_id' => $this->gst->id, 'position' => 1]);
    $this->manager = makeStaff(Permission::ManageCategories);
});

it('shows the whole tree, hidden categories and course counts included, only to category managers', function () {
    $this->itc->update(['is_active' => false]);
    draftFor();

    $this->actingAs(makeUser(Role::Instructor))->getJson('/api/v1/admin/categories')->assertForbidden();
    $this->actingAs(makeStaff(Permission::ManageCourses))->getJson('/api/v1/admin/categories')->assertForbidden();

    $this->actingAs($this->manager)->getJson('/api/v1/admin/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'gst')
        ->assertJsonCount(2, 'data.0.children')
        ->assertJsonPath('data.0.children.0.course_count', 1)
        ->assertJsonPath('data.0.children.0.published_count', 0)
        ->assertJsonPath('data.0.children.1.is_active', false);
});

it('creates areas and subcategories with a generated address', function () {
    $this->actingAs($this->manager)->postJson('/api/v1/admin/categories', ['name' => 'E-invoicing', 'parent_id' => $this->gst->id])
        ->assertCreated()->assertJsonPath('data.slug', 'e-invoicing')->assertJsonPath('data.position', 2);
    $this->actingAs($this->manager)->postJson('/api/v1/admin/categories', ['name' => 'GST'])
        ->assertCreated()->assertJsonPath('data.slug', 'gst-2')->assertJsonPath('data.parent_id', null);

    // Three levels aren't allowed.
    $this->actingAs($this->manager)->postJson('/api/v1/admin/categories', ['name' => 'Too deep', 'parent_id' => $this->returns->id])
        ->assertUnprocessable()->assertJsonValidationErrorFor('parent_id', 'error.fields');
    $this->actingAs($this->manager)->postJson('/api/v1/admin/categories', ['name' => 'Bad', 'slug' => 'Not A Slug'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('slug', 'error.fields');

    expect(AuditLog::query()->where('action', 'category.created')->count())->toBe(2);
});

it('renames, hides and moves categories within the rules', function () {
    $this->actingAs($this->manager)->patchJson("/api/v1/admin/categories/{$this->itc->id}", ['name' => 'ITC', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.name', 'ITC')->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/categories/input-tax-credit')->assertNotFound();

    $this->actingAs($this->manager)->patchJson("/api/v1/admin/categories/{$this->itc->id}", ['parent_id' => $this->tax->id])
        ->assertOk()->assertJsonPath('data.parent_id', $this->tax->id);

    // An area with subcategories can't go under another area.
    $this->actingAs($this->manager)->patchJson("/api/v1/admin/categories/{$this->gst->id}", ['parent_id' => $this->tax->id])
        ->assertUnprocessable()->assertJsonPath('error.code', 'HAS_SUBCATEGORIES');

    // A subcategory holding courses can't become an area.
    draftFor();
    $this->actingAs($this->manager)->patchJson("/api/v1/admin/categories/{$this->returns->id}", ['parent_id' => null])
        ->assertUnprocessable()->assertJsonPath('error.code', 'HAS_COURSES');
});

it('hides subcategories of a hidden area from the public site', function () {
    $this->actingAs($this->manager)->patchJson("/api/v1/admin/categories/{$this->gst->id}", ['is_active' => false])->assertOk();

    $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/categories/registration-returns')->assertNotFound();
});

it('reorders one level, insisting on the complete list', function () {
    $this->actingAs($this->manager)->putJson('/api/v1/admin/categories/order', ['parent_id' => $this->gst->id, 'ids' => [$this->itc->id]])
        ->assertUnprocessable()->assertJsonPath('error.code', 'ORDER_MISMATCH');

    $this->actingAs($this->manager)->putJson('/api/v1/admin/categories/order', ['parent_id' => $this->gst->id, 'ids' => [$this->itc->id, $this->returns->id]])
        ->assertNoContent();
    $this->actingAs($this->manager)->putJson('/api/v1/admin/categories/order', ['parent_id' => null, 'ids' => [$this->tax->id, $this->gst->id]])
        ->assertNoContent();

    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.slug', 'income-tax')->assertJsonPath('data.1.children.0.slug', 'input-tax-credit');
});

it('deletes only empty categories', function () {
    draftFor();

    $this->actingAs($this->manager)->deleteJson("/api/v1/admin/categories/{$this->returns->id}")
        ->assertUnprocessable()->assertJsonPath('error.code', 'HAS_COURSES');
    $this->actingAs($this->manager)->deleteJson("/api/v1/admin/categories/{$this->gst->id}")
        ->assertUnprocessable()->assertJsonPath('error.code', 'HAS_SUBCATEGORIES');

    $this->actingAs($this->manager)->deleteJson("/api/v1/admin/categories/{$this->itc->id}")->assertNoContent();
    expect(Category::query()->whereKey($this->itc->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'category.deleted')->exists())->toBeTrue();
});
