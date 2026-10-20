<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;

/** Small rules shared by the catalogue, the cart and the player. */
class Editions
{
    /** What a buyer gets if they don't choose: the recommended edition, else the entry level. */
    public function default(Course $course): ?CourseEdition
    {
        $editions = $course->editions;

        return $editions->firstWhere('is_recommended', true) ?? $editions->first();
    }

    /**
     * Price to move from one edition to a higher one: the difference in list price. Null
     * when it isn't an upgrade (same or lower edition).
     */
    public function upgradePrice(CourseEdition $from, CourseEdition $to): ?int
    {
        if ($to->course_id !== $from->course_id || $to->position <= $from->position) {
            return null;
        }

        return max(0, $to->price() - $from->price());
    }

    /**
     * Ids of the editions whose content an edition includes (itself and everything below).
     *
     * @return list<int>
     */
    public function coveredIds(CourseEdition $edition): array
    {
        return CourseEdition::query()->where('course_id', $edition->course_id)->where('position', '<=', $edition->position)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Keep the course's own price at its cheapest edition, so catalogue cards, search and
     * sorting show a "from" price without knowing about editions.
     */
    public function syncCoursePrice(Course $course): void
    {
        $cheapest = $course->editions()->with('priceTier')->get()->sortBy(fn (CourseEdition $e) => $e->price())->first();
        if ($cheapest !== null && $course->price_tier_id !== $cheapest->price_tier_id) {
            $course->forceFill(['price_tier_id' => $cheapest->price_tier_id, 'pricing_confirmed_at' => now()])->save();
        }
    }
}
