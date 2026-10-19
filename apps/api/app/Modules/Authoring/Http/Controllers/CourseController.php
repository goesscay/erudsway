<?php

namespace App\Modules\Authoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authoring\Http\Requests\UpdateCourseRequest;
use App\Modules\Authoring\Http\Resources\CourseEditorResource;
use App\Modules\Authoring\Http\Resources\InstructorCourseResource;
use App\Modules\Authoring\Http\Resources\PriceTierResource;
use App\Modules\Authoring\Models\CourseSubmission;
use App\Modules\Authoring\Services\CourseReadiness;
use App\Modules\Authoring\Services\ThumbnailProcessor;
use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /** The signed-in instructor's courses, most recently edited first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->where('instructor_id', $this->user($request)->id)
            ->with(['category', 'priceTier'])
            ->latest('updated_at')
            ->get();

        return InstructorCourseResource::collection($courses);
    }

    /** Start a new draft course. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'category' => ['required', 'string', Rule::exists('categories', 'slug')->whereNotNull('parent_id')->where('is_active', true)],
        ], ['category.exists' => 'Choose one of the listed subcategories.']);

        $category = Category::query()->where('slug', $data['category'])->firstOrFail();

        $course = Course::query()->create([
            'instructor_id' => $this->user($request)->id,
            'category_id' => $category->id,
            'price_tier_id' => null,
            'slug' => $this->uniqueSlug($data['title']),
            'title' => $data['title'],
            'level' => CourseLevel::All,
            'language' => 'English',
        ]);
        $course->sections()->create(['title' => 'Introduction', 'position' => 1]);

        return CourseEditorResource::load($course)->response()->setStatusCode(201);
    }

    /** Full course data for the builder. */
    public function show(Course $course): CourseEditorResource
    {
        Gate::authorize('view', $course);

        return CourseEditorResource::load($course);
    }

    /** Update the landing page, details or price. */
    public function update(UpdateCourseRequest $request, Course $course, HtmlSanitizer $sanitizer, AuditLogger $audit): CourseEditorResource
    {
        Gate::authorize('update', $course);
        $data = $request->validated();

        if (array_key_exists('description', $data)) {
            $data['description'] = $sanitizer->clean($data['description']);
        }
        if (array_key_exists('category', $data)) {
            $data['category_id'] = Category::query()->where('slug', $data['category'])->value('id');
            unset($data['category']);
        }
        $pricingTouched = array_key_exists('price_tier_id', $data);

        $before = $course->only(['title', 'price_tier_id', 'category_id']);
        $course->fill($data);
        if ($pricingTouched) {
            $course->price_tier_id = $data['price_tier_id'];
            $course->pricing_confirmed_at = now();
        }
        $course->save();

        // Changes to a live course take effect immediately; keep a record of what changed.
        if ($course->status === CourseStatus::Published && $course->wasChanged(['title', 'price_tier_id', 'category_id'])) {
            $audit->record('course.live_details_changed', $course, $before, $course->only(['title', 'price_tier_id', 'category_id']));
        }

        return CourseEditorResource::load($course);
    }

    /** Upload the course image (JPEG, PNG or WebP; at least 750×422). */
    public function thumbnail(Request $request, Course $course, ThumbnailProcessor $processor): CourseEditorResource
    {
        Gate::authorize('update', $course);
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('media.thumbnails.max_kb')],
        ]);

        $processor->store($course, $request->file('image'));

        return CourseEditorResource::load($course);
    }

    /** Delete a draft that has never been published. */
    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);
        $course->forceDelete();

        return response()->noContent();
    }

    /** Send the course to the Erudsway team for review. */
    public function submit(Request $request, Course $course, CourseReadiness $readiness, AuditLogger $audit): CourseEditorResource
    {
        Gate::authorize('submit', $course);

        if (! $readiness->isReady($course)) {
            throw new DomainException('COURSE_NOT_READY', 'Complete every item on the checklist before submitting.', 422);
        }

        DB::transaction(function () use ($request, $course, $audit) {
            $course->forceFill(['status' => CourseStatus::InReview])->save();
            CourseSubmission::query()->create([
                'course_id' => $course->id,
                'submitted_by' => $this->user($request)->id,
                'status' => 'pending',
            ]);
            $audit->record('course.submitted', $course);
        });

        return CourseEditorResource::load($course->refresh());
    }

    /** Price tiers instructors can choose from. */
    public function priceTiers(): AnonymousResourceCollection
    {
        return PriceTierResource::collection(PriceTier::query()->where('is_active', true)->orderBy('amount')->get());
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'course';
        $slug = $base;
        while (Course::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
