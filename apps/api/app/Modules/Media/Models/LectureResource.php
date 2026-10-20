<?php

namespace App\Modules\Media\Models;

use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A downloadable file attached to a lecture (stored on the private disk).
 *
 * @property int $id
 * @property int $lecture_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property int|null $min_edition_id
 * @property-read CourseEdition|null $minEdition
 */
class LectureResource extends Model
{
    protected $fillable = ['path', 'original_name', 'mime', 'size_bytes', 'min_edition_id'];

    /** @return BelongsTo<CourseEdition, $this> */
    public function minEdition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'min_edition_id');
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}
