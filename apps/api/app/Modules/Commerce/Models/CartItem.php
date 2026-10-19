<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $course_id
 * @property-read Course $course
 */
class CartItem extends Model
{
    protected $fillable = ['course_id'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
