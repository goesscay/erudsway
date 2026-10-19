<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property-read Collection<int, Lecture> $lectures
 */
class CourseSection extends Model
{
    protected $fillable = ['title', 'position'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Lecture, $this> */
    public function lectures(): HasMany
    {
        return $this->hasMany(Lecture::class, 'section_id')->orderBy('position');
    }
}
