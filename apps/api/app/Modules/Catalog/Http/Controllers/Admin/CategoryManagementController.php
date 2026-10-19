<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Http\Resources\AdminCategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The two-level category tree: areas on top, subcategories beneath. Courses always sit in a
 * subcategory. A category that still holds courses (or subcategories) can't be deleted —
 * hide it instead. Requires the `manage_categories` permission.
 */
class CategoryManagementController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** The whole tree, hidden categories included, with course counts. */
    public function index(): AnonymousResourceCollection
    {
        $counts = [
            'courses',
            'courses as published_courses_count' => fn (Builder $q) => $q->where('status', CourseStatus::Published->value),
        ];
        $areas = Category::query()
            ->whereNull('parent_id')
            ->withCount($counts)
            ->with(['allChildren' => fn (HasMany $q) => $q->withCount($counts)])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return AdminCategoryResource::collection($areas);
    }

    public function store(Request $request): AdminCategoryResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:300'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
        ], $this->messages());

        $slug = $data['slug'] ?? $this->uniqueSlug($data['name']);
        $category = Category::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'position' => (int) Category::query()->where('parent_id', $data['parent_id'] ?? null)->max('position') + 1,
            'is_active' => true,
        ]);
        $this->audit->record('category.created', $category, null, $category->only(['name', 'slug', 'parent_id']));

        return new AdminCategoryResource($category);
    }

    /** Rename, re-describe, move a subcategory to another area, or hide/show. */
    public function update(Request $request, Category $category): AdminCategoryResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'slug' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->messages());

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $data['parent_id'] === null ? null : (int) $data['parent_id'];
        }
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $category->parent_id) {
            if ($data['parent_id'] === $category->id) {
                throw new DomainException('INVALID_PARENT', 'A category can’t sit inside itself.', 422);
            }
            if ($data['parent_id'] !== null && $category->allChildren()->exists()) {
                throw new DomainException('HAS_SUBCATEGORIES', 'Move or delete this area’s subcategories before placing it under another area.', 422);
            }
            if ($data['parent_id'] === null && $category->courses()->withTrashed()->exists()) {
                throw new DomainException('HAS_COURSES', 'Courses must sit in a subcategory. Move its courses first.', 422);
            }
            $data['position'] = (int) Category::query()->where('parent_id', $data['parent_id'])->max('position') + 1;
        }

        $before = $category->only(array_keys($data));
        $category->fill($data)->save();
        if ($category->wasChanged()) {
            $this->audit->record('category.updated', $category, $before, $category->only(array_keys($data)));
        }

        return new AdminCategoryResource($category);
    }

    /** Set the order of one level: all areas, or the subcategories of one area. */
    public function reorder(Request $request): Response
    {
        $data = $request->validate([
            'parent_id' => ['present', 'nullable', 'integer', 'exists:categories,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $siblings = Category::query()->where('parent_id', $data['parent_id'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $ids = $request->collect('ids')->map(fn ($id) => (int) $id)->values()->all();
        $given = collect($ids)->sort()->values()->all();
        if ($siblings !== $given) {
            throw new DomainException('ORDER_MISMATCH', 'The list must contain every category on that level exactly once. Reload and try again.', 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                Category::query()->whereKey($id)->update(['position' => $position]);
            }
        });
        $this->audit->record('category.reordered', null, null, ['parent_id' => $data['parent_id'], 'ids' => $ids]);

        return response()->noContent();
    }

    /** Only empty categories can be deleted; anything with courses or subcategories is hidden instead. */
    public function destroy(Category $category): Response
    {
        if ($category->allChildren()->exists()) {
            throw new DomainException('HAS_SUBCATEGORIES', 'Delete or move its subcategories first, or hide it instead.', 422);
        }
        if (Course::withTrashed()->where('category_id', $category->id)->exists()) {
            throw new DomainException('HAS_COURSES', 'This category still has courses. Hide it instead, or move its courses first.', 422);
        }

        $category->delete();
        $this->audit->record('category.deleted', $category, $category->only(['name', 'slug', 'parent_id']), null);

        return response()->noContent();
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
            'slug.unique' => 'Another category already uses that address.',
            'parent_id.exists' => 'Choose one of the top-level areas.',
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        for ($i = 2; Category::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
