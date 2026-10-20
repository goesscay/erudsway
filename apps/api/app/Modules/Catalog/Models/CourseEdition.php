<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One level a course is sold at. Higher positions include everything in lower ones.
 *
 * @property int $id
 * @property int $course_id
 * @property int $position
 * @property string $name
 * @property int $price_tier_id
 * @property list<string> $includes
 * @property bool $is_recommended
 * @property int|null $access_days
 * @property int|null $video_view_limit
 * @property bool $grants_simulator
 * @property bool $includes_doubt_session
 * @property-read Course $course
 * @property-read PriceTier $priceTier
 */
class CourseEdition extends Model
{
    protected $fillable = [
        'course_id', 'position', 'name', 'price_tier_id', 'includes', 'is_recommended',
        'access_days', 'video_view_limit', 'grants_simulator', 'includes_doubt_session',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['includes' => '[]'];

    protected function casts(): array
    {
        return [
            'includes' => 'array',
            'is_recommended' => 'boolean',
            'access_days' => 'integer',
            'video_view_limit' => 'integer',
            'grants_simulator' => 'boolean',
            'includes_doubt_session' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<PriceTier, $this> */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function price(): int
    {
        return (int) $this->priceTier->amount;
    }

    /** Whether this edition includes content held back to $minimum (null = available to every edition). */
    public function covers(?CourseEdition $minimum): bool
    {
        return $minimum === null || $this->position >= $minimum->position;
    }
}
