<?php

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Http\Resources\ReviewResource;
use App\Modules\Engagement\Models\Review;
use App\Modules\Engagement\Services\Reviews;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private readonly Reviews $reviews) {}

    /** Public reviews of a live course, newest first, with the rating breakdown. */
    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $data = $request->validate(['rating' => ['nullable', 'integer', 'between:1,5'], 'page' => ['nullable', 'integer', 'min:1']]);
        $course = Course::query()->published()->where('slug', $slug)->firstOrFail();

        $counts = Review::query()->public()->where('course_id', $course->id)
            ->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $total = (int) $counts->sum();

        $page = Review::query()->public()->where('course_id', $course->id)
            ->when($data['rating'] ?? null, fn ($q, $r) => $q->where('rating', $r))
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return ReviewResource::collection($page)->additional(['summary' => [
            'average' => $total > 0 ? round((float) Review::query()->public()->where('course_id', $course->id)->avg('rating'), 1) : 0,
            'count' => $total,
            /** Share of reviews at each star level, 5 down to 1, in whole percent. */
            'distribution' => collect([5, 4, 3, 2, 1])->map(fn (int $star) => [
                'rating' => $star,
                'count' => (int) ($counts[$star] ?? 0),
                'percent' => $total > 0 ? (int) round(((int) ($counts[$star] ?? 0)) * 100 / $total) : 0,
            ])->all(),
        ]]);
    }

    /** The signed-in learner's own review of a course, if any. */
    public function mine(Request $request, string $slug): JsonResponse
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $review = Review::query()->where('user_id', $this->user($request)->id)->where('course_id', $course->id)->with('user:id,name')->first();

        return ApiResponse::data($review ? (new ReviewResource($review))->toArray($request) : null);
    }

    public function save(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $review = $this->reviews->save($this->user($request), $course, $data['rating'], $data['body'] ?? null);

        return (new ReviewResource($review->load('user:id,name')))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, string $slug): Response
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $this->reviews->delete($this->user($request), $course);

        return response()->noContent();
    }

    public function report(Request $request, Review $review): Response
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        abort_if($review->status === ReviewStatus::Hidden, 404);
        $this->reviews->report($this->user($request), $review, $data['reason']);

        return response()->noContent();
    }

    /** Instructor: reviews of my courses, newest first; optionally only those without a reply. */
    public function forInstructor(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['unreplied' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1']]);

        return ReviewResource::collection(
            Review::query()->public()
                ->whereHas('course', fn ($q) => $q->where('instructor_id', $this->user($request)->id))
                ->when($request->boolean('unreplied'), fn ($q) => $q->whereNull('instructor_reply'))
                ->with(['user:id,name', 'course:id,slug,title'])
                ->latest()
                ->paginate(20),
        );
    }

    public function reply(Request $request, Review $review): ReviewResource
    {
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:2000']]);

        return new ReviewResource($this->reviews->reply($this->user($request), $review->load('course'), $data['reply'] ?? null)->load(['user:id,name', 'course:id,slug,title']));
    }

    /** Staff: the moderation queue (flagged by default). */
    public function moderationQueue(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(ReviewStatus::class)]]);

        return ReviewResource::collection(
            Review::query()->where('status', $data['status'] ?? ReviewStatus::Flagged->value)
                ->with(['user:id,name', 'course:id,slug,title', 'reports'])
                ->orderByDesc('report_count')->latest()
                ->get(),
        );
    }

    public function moderate(Request $request, Review $review): ReviewResource
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['hide', 'restore'])],
            'note' => ['nullable', 'required_if:action,hide', 'string', 'max:500'],
        ], ['note.required_if' => 'Say why the review is being hidden.']);

        return new ReviewResource($this->reviews->moderate($this->user($request), $review->load('course'), $data['action'] === 'hide', $data['note'] ?? null)->load(['user:id,name', 'course:id,slug,title']));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
