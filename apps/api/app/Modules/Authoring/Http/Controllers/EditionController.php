<?php

namespace App\Modules\Authoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authoring\Http\Resources\CourseEditorResource;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Services\Editions;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Media\Models\LectureResource;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Editions in the course builder: the levels a course is sold at, entry level first. Each
 * includes everything below it. Simulator access and the doubt session are Erudsway services,
 * so only catalogue managers can switch them on.
 */
class EditionController extends Controller
{
    public function __construct(private readonly Editions $editions) {}

    public function store(Request $request, Course $course): CourseEditorResource
    {
        Gate::authorize('update', $course);
        $data = $this->validated($request);
        if ($course->editions()->count() >= 6) {
            throw new DomainException('TOO_MANY_EDITIONS', 'A course can have at most six editions.', 422);
        }

        DB::transaction(function () use ($course, $data) {
            $edition = $course->editions()->create([...$data, 'position' => (int) $course->editions()->max('position') + 1]);
            $this->onlyOneRecommended($edition);
        });
        $this->editions->syncCoursePrice($course);

        return CourseEditorResource::load($course->refresh());
    }

    public function update(Request $request, CourseEdition $edition): CourseEditorResource
    {
        Gate::authorize('update', $edition->course);
        $data = $this->validated($request, partial: true);

        DB::transaction(function () use ($edition, $data) {
            $edition->fill($data)->save();
            $this->onlyOneRecommended($edition);
        });
        $this->editions->syncCoursePrice($edition->course);

        return CourseEditorResource::load($edition->course->refresh());
    }

    /** An edition someone has bought (or has in an order) can't be removed. */
    public function destroy(CourseEdition $edition): CourseEditorResource
    {
        $course = $edition->course;
        Gate::authorize('update', $course);
        if (Enrollment::query()->where('edition_id', $edition->id)->exists() || OrderItem::query()->where('edition_id', $edition->id)->orWhere('upgrade_from_edition_id', $edition->id)->exists()) {
            throw new DomainException('EDITION_IN_USE', 'Learners have bought this edition, so it can’t be deleted. Rename or reprice it instead.', 409);
        }

        DB::transaction(function () use ($edition, $course) {
            $edition->delete();
            $this->renumber($course, $course->editions()->pluck('id')->map(fn ($id) => (int) $id)->all());
        });
        $this->editions->syncCoursePrice($course);

        return CourseEditorResource::load($course->refresh());
    }

    /** Order editions entry level first; each includes those before it. */
    public function reorder(Request $request, Course $course): CourseEditorResource
    {
        Gate::authorize('update', $course);
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);
        $ids = $request->collect('ids')->map(fn ($id) => (int) $id)->values()->all();
        $current = $course->editions()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if (collect($ids)->sort()->values()->all() !== $current) {
            throw new DomainException('ORDER_MISMATCH', 'Send every edition of this course exactly once.', 422);
        }

        DB::transaction(fn () => $this->renumber($course, $ids));

        return CourseEditorResource::load($course->refresh());
    }

    /** Hold a downloadable file back to an edition (null: available to every edition). */
    public function resourceEdition(Request $request, LectureResource $resource): CourseEditorResource
    {
        $course = $resource->lecture->course;
        Gate::authorize('update', $course);
        $data = $request->validate(['min_edition_id' => ['present', 'nullable', 'integer', Rule::exists('course_editions', 'id')->where('course_id', $course->id)]]);
        $resource->forceFill(['min_edition_id' => $data['min_edition_id']])->save();

        return CourseEditorResource::load($course);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:60'],
            'price_tier_id' => [$required, 'integer', Rule::exists('price_tiers', 'id')->where('is_active', true)],
            'includes' => ['sometimes', 'array', 'max:10'],
            'includes.*' => ['string', 'min:2', 'max:160'],
            'is_recommended' => ['sometimes', 'boolean'],
            'access_days' => ['sometimes', 'nullable', 'integer', 'between:1,3650'],
            'video_view_limit' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'grants_simulator' => ['sometimes', 'boolean'],
            'includes_doubt_session' => ['sometimes', 'boolean'],
        ], ['price_tier_id.exists' => 'Choose one of the listed prices.']);

        /** @var User $user */
        $user = $request->user();
        if ((isset($data['grants_simulator']) || isset($data['includes_doubt_session'])) && ! $user->can(Permission::ManageCourses->value)) {
            throw new DomainException('STAFF_ONLY', 'Simulator access and doubt sessions are set up by the Erudsway team.', 403);
        }
        if (isset($data['includes'])) {
            $data['includes'] = array_values(array_filter(array_map('trim', $data['includes'])));
        }

        return $data;
    }

    private function onlyOneRecommended(CourseEdition $edition): void
    {
        if ($edition->is_recommended) {
            CourseEdition::query()->where('course_id', $edition->course_id)->whereKeyNot($edition->id)->update(['is_recommended' => false]);
        }
    }

    /**
     * Positions 1..n in the given order (moved out of the way first, as positions are unique).
     *
     * @param  list<int>  $ids
     */
    private function renumber(Course $course, array $ids): void
    {
        CourseEdition::query()->where('course_id', $course->id)->update(['position' => DB::raw('position + 1000')]);
        foreach ($ids as $i => $id) {
            CourseEdition::query()->whereKey($id)->update(['position' => $i + 1]);
        }
    }
}
