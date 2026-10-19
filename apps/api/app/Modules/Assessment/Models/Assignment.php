<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The brief behind an assignment lecture. Learners answer in text and/or one file.
 *
 * @property int $id
 * @property int $lecture_id
 * @property string|null $instructions_html Sanitised with HTMLPurifier on save
 * @property bool $allow_file
 * @property-read Lecture $lecture
 */
class Assignment extends Model
{
    protected $fillable = ['lecture_id', 'instructions_html', 'allow_file'];

    protected $attributes = ['allow_file' => true];

    protected function casts(): array
    {
        return ['allow_file' => 'boolean'];
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}
