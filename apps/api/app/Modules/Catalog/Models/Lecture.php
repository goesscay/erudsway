<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\LectureKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property int $section_id
 * @property string $title
 * @property LectureKind $kind
 * @property int $position
 * @property int $duration_seconds
 * @property bool $is_free_preview
 */
class Lecture extends Model
{
    protected $fillable = ['course_id', 'title', 'kind', 'position', 'duration_seconds', 'is_free_preview'];

    protected function casts(): array
    {
        return [
            'kind' => LectureKind::class,
            'duration_seconds' => 'integer',
            'is_free_preview' => 'boolean',
        ];
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }
}
