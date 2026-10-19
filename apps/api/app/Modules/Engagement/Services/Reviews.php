<?php

namespace App\Modules\Engagement\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\Review;
use App\Modules\Engagement\Notifications\NewReview;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ratings and reviews (one per learner per course). The course's rating is recomputed from
 * public reviews whenever one changes, so the catalogue's numbers are always real.
 */
class Reviews
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    /** Create or update the learner's review. Only enrolled learners can review. */
    public function save(User $user, Course $course, int $rating, ?string $body): Review
    {
        if ($this->entitlements->role($user, $course) !== Entitlements::ROLE_LEARNER) {
            throw new DomainException('NOT_ENROLLED', 'Only learners enrolled in this course can review it.', 403);
        }

        $review = Review::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
        $isNew = $review === null;
        $review ??= new Review(['user_id' => $user->id, 'course_id' => $course->id]);
        if ($review->status === ReviewStatus::Hidden) {
            throw new DomainException('REVIEW_HIDDEN', 'Your review was removed by our moderators and can’t be edited.', 409);
        }

        $review->fill(['rating' => $rating, 'body' => $body !== null && trim($body) !== '' ? trim($body) : null]);
        $review->save();
        $this->refreshRating($course);

        if ($isNew) {
            $course->instructor->notify(new NewReview($review));
        }

        return $review;
    }

    public function delete(User $user, Course $course): void
    {
        Review::query()->where('user_id', $user->id)->where('course_id', $course->id)->where('status', '!=', ReviewStatus::Hidden->value)->delete();
        $this->refreshRating($course);
    }

    /** The course's instructor answers a review publicly (once; can be edited). */
    public function reply(User $instructor, Review $review, ?string $reply): Review
    {
        if ($review->course->instructor_id !== $instructor->id) {
            throw new DomainException('NOT_FOUND', 'Review not found.', 404);
        }
        $reply = $reply !== null && trim($reply) !== '' ? trim($reply) : null;
        $review->forceFill(['instructor_reply' => $reply, 'replied_at' => $reply ? now() : null])->save();

        return $review;
    }

    /** Anyone signed in can report a review; it stays up but goes to the moderation queue. */
    public function report(User $user, Review $review, string $reason): void
    {
        if ($review->user_id === $user->id) {
            throw new DomainException('OWN_REVIEW', 'You can’t report your own review.', 422);
        }

        DB::transaction(function () use ($user, $review, $reason) {
            $inserted = DB::table('review_reports')->insertOrIgnore([
                'review_id' => $review->id, 'user_id' => $user->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted > 0) {
                $review->increment('report_count');
                if ($review->status === ReviewStatus::Visible) {
                    $review->forceFill(['status' => ReviewStatus::Flagged])->save();
                }
            }
        });
    }

    /** Staff decision on a flagged review: hide it, or clear the flag. */
    public function moderate(User $staff, Review $review, bool $hide, ?string $note): Review
    {
        $before = $review->status->value;
        $review->forceFill([
            'status' => $hide ? ReviewStatus::Hidden : ReviewStatus::Visible,
            'moderation_note' => $note,
            'moderated_by' => $staff->id,
        ])->save();
        $this->refreshRating($review->course);
        $this->audit->record($hide ? 'review.hidden' : 'review.restored', $review, ['status' => $before], ['status' => $review->status->value, 'note' => $note], $staff);

        return $review;
    }

    public function refreshRating(Course $course): void
    {
        $stats = Review::query()->public()->where('course_id', $course->id)
            ->selectRaw('COUNT(*) as n, AVG(rating) as avg')->first();
        $count = (int) $stats->getAttribute('n');

        $course->forceFill([
            'rating_count' => $count,
            'rating_avg' => $count > 0 ? round((float) $stats->getAttribute('avg'), 2) : 0,
        ])->saveQuietly();
    }
}
