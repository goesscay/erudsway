<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two levels: top-level categories (Development) and subcategories (Web Development).
 * Courses attach to subcategories.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $position
 * @property bool $is_active
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Category> $allChildren
 * @property-read int|null $courses_count
 * @property-read int|null $published_courses_count
 */
class Category extends Model
{
    protected $fillable = ['parent_id', 'slug', 'name', 'description', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('is_active', true)->orderBy('position');
    }

    /**
     * Subcategories including hidden ones (for the admin tree).
     *
     * @return HasMany<Category, $this>
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * This category's id plus its active children's ids — what a course filter should match.
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }
}
