<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $course_id
 * @property int|null $edition_id
 * @property-read Course $course
 * @property-read CourseEdition|null $edition
 */
class CartItem extends Model
{
    protected $fillable = ['course_id', 'edition_id'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseEdition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'edition_id');
    }
}
