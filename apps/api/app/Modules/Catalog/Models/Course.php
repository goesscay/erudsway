<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Identity\Models\InstructorProfile;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $instructor_id
 * @property int $category_id
 * @property int|null $price_tier_id
 * @property string $slug
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $description
 * @property string $language
 * @property CourseLevel $level
 * @property CourseStatus $status
 * @property string|null $thumbnail_path
 * @property list<string> $outcomes
 * @property list<string> $requirements
 * @property list<string> $audience
 * @property Carbon|null $published_at
 * @property float $rating_avg
 * @property int $rating_count
 * @property int $enrollment_count
 * @property int $duration_seconds
 * @property int $lecture_count
 * @property Carbon $updated_at
 * @property-read User $instructor
 * @property-read InstructorProfile|null $instructorProfile
 * @property-read Category $category
 * @property-read PriceTier|null $priceTier
 * @property-read Collection<int, CourseSection> $sections
 */
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'instructor_id', 'category_id', 'price_tier_id', 'slug', 'title', 'subtitle', 'description',
        'language', 'level', 'thumbnail_path', 'outcomes', 'requirements', 'audience',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'outcomes' => '[]',
        'requirements' => '[]',
        'audience' => '[]',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'status' => CourseStatus::class,
            'outcomes' => 'array',
            'requirements' => 'array',
            'audience' => 'array',
            'published_at' => 'datetime',
            'rating_avg' => 'float',
            'rating_count' => 'integer',
            'enrollment_count' => 'integer',
            'duration_seconds' => 'integer',
            'lecture_count' => 'integer',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return HasOne<InstructorProfile, $this> */
    public function instructorProfile(): HasOne
    {
        return $this->hasOne(InstructorProfile::class, 'user_id', 'instructor_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<PriceTier, $this> */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    /** @return HasMany<CourseSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('position');
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Published->value)->whereNotNull('published_at');
    }

    public function isFree(): bool
    {
        return $this->price_tier_id === null;
    }

    /** Thumbnails live on the public disk; seeded demo data may use absolute URLs. */
    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path === null) {
            return null;
        }

        return Str::startsWith($this->thumbnail_path, ['http://', 'https://'])
            ? $this->thumbnail_path
            : Storage::disk('public')->url($this->thumbnail_path);
    }

    /** "bestseller" | "highest_rated" | "new" | null */
    public function badge(): ?string
    {
        $rules = config('catalog.badges');

        return match (true) {
            $this->enrollment_count >= $rules['bestseller_min_enrollments'] => 'bestseller',
            $this->rating_avg >= $rules['highest_rated_min_rating'] && $this->rating_count >= $rules['highest_rated_min_reviews'] => 'highest_rated',
            $this->published_at !== null && $this->published_at->gt(now()->subDays($rules['new_within_days'])) => 'new',
            default => null,
        };
    }
}
