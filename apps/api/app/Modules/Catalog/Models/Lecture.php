<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Media\Models\LectureResource;
use App\Modules\Media\Models\VideoAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property int $section_id
 * @property string $title
 * @property LectureKind $kind
 * @property int|null $video_asset_id
 * @property string|null $article_html
 * @property int $position
 * @property int $duration_seconds
 * @property bool $is_free_preview
 * @property-read Course $course
 * @property-read CourseSection $section
 * @property-read VideoAsset|null $videoAsset
 * @property-read Collection<int, LectureResource> $resources
 */
class Lecture extends Model
{
    protected $fillable = ['course_id', 'title', 'kind', 'position', 'duration_seconds', 'is_free_preview', 'article_html'];

    protected function casts(): array
    {
        return [
            'kind' => LectureKind::class,
            'duration_seconds' => 'integer',
            'is_free_preview' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /** @return BelongsTo<VideoAsset, $this> */
    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class);
    }

    /** @return HasMany<LectureResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(LectureResource::class);
    }
}
