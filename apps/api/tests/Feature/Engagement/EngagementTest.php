<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Engagement\Jobs\SendAnnouncement;
use App\Modules\Engagement\Models\Announcement;
use App\Modules\Engagement\Models\NotificationPreference;
use App\Modules\Engagement\Models\Question;
use App\Modules\Engagement\Notifications\CourseAnnouncement;
use App\Modules\Engagement\Notifications\NewAnswer;
use App\Modules\Engagement\Notifications\NewQuestion;
use App\Modules\Engagement\Notifications\NewReview;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

it('lets enrolled learners rate a course once, and keeps the course rating real', function () {
    $course = liveCourse();
    $outsider = makeUser();
    $this->actingAs($outsider)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 5])->assertForbidden();
    $this->actingAs($course->instructor)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 5])->assertForbidden();

    $a = named('Priya Raman');
    $b = makeUser();
    enrollIn($course, $a);
    enrollIn($course, $b);
    $this->actingAs($a)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 5, 'body' => 'Clear, practical, and the GSTR-9 walkthrough saved me hours.'])
        ->assertOk()->assertJsonPath('data.author', 'Priya R.');
    $this->actingAs($b)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 2]);
    $this->actingAs($b)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 4]); // edit, not a second review

    expect($course->fresh())->rating_count->toBe(2)->rating_avg->toEqual(4.5);
    Notification::assertSentToTimes($course->instructor, NewReview::class, 2);

    $this->getJson("/api/v1/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('summary.count', 2)
        ->assertJsonPath('summary.distribution.0', ['rating' => 5, 'count' => 1, 'percent' => 50]);
    $this->getJson("/api/v1/courses/{$course->slug}/reviews?rating=4")->assertJsonCount(1, 'data');

    $this->actingAs($b)->deleteJson("/api/v1/courses/{$course->slug}/review")->assertNoContent();
    expect($course->fresh())->rating_count->toBe(1)->rating_avg->toEqual(5.0);
});

it('lets the instructor reply, and only on their own courses', function () {
    $course = liveCourse();
    $learner = makeUser();
    enrollIn($course, $learner);
    $id = $this->actingAs($learner)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 3, 'body' => 'Good but needs more on ITC.'])->json('data.id');

    $this->actingAs(liveCourse()->instructor)->putJson("/api/v1/instructor/reviews/{$id}/reply", ['reply' => 'Thanks'])->assertNotFound();
    $this->actingAs($course->instructor)->putJson("/api/v1/instructor/reviews/{$id}/reply", ['reply' => 'Thanks — an ITC section is coming next month.'])
        ->assertJsonPath('data.instructor_reply', 'Thanks — an ITC section is coming next month.');
    $this->actingAs($course->instructor)->getJson('/api/v1/instructor/reviews?unreplied=1')->assertJsonCount(0, 'data');
});

it('flags reported reviews for moderators, who can hide them', function () {
    $course = liveCourse();
    $author = makeUser();
    enrollIn($course, $author);
    $id = $this->actingAs($author)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 1, 'body' => 'Buy my course instead at example.com'])->json('data.id');

    $this->actingAs($author)->postJson("/api/v1/reviews/{$id}/report", ['reason' => 'Mine'])->assertUnprocessable();
    $reporter = makeUser();
    $this->actingAs($reporter)->postJson("/api/v1/reviews/{$id}/report", ['reason' => 'Spam and self-promotion'])->assertNoContent();
    $this->actingAs($reporter)->postJson("/api/v1/reviews/{$id}/report", ['reason' => 'Spam again'])->assertNoContent(); // counted once

    $mod = makeStaff(Permission::ModerateCourses);
    $this->actingAs(makeUser())->getJson('/api/v1/admin/reviews')->assertForbidden();
    $this->actingAs($mod)->getJson('/api/v1/admin/reviews')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.report_count', 1)->assertJsonPath('data.0.reports.0', 'Spam and self-promotion');

    $this->actingAs($mod)->postJson("/api/v1/reviews/{$id}/moderate", ['action' => 'hide'])->assertNotFound(); // admin route only
    $this->actingAs($mod)->postJson("/api/v1/admin/reviews/{$id}/moderate", ['action' => 'hide'])->assertUnprocessable();
    $this->actingAs($mod)->postJson("/api/v1/admin/reviews/{$id}/moderate", ['action' => 'hide', 'note' => 'Promotional content'])->assertJsonPath('data.status', 'hidden');

    $this->getJson("/api/v1/courses/{$course->slug}/reviews")->assertJsonCount(0, 'data');
    expect($course->fresh()->rating_count)->toBe(0);
    $this->actingAs($author)->putJson("/api/v1/courses/{$course->slug}/review", ['rating' => 5])->assertStatus(409);
});

it('runs course Q&A: learners ask, the instructor answers, people are told', function () {
    $course = liveCourse();
    $learner = makeUser();
    enrollIn($course, $learner);
    $lecture = $course->lectures()->firstOrFail();

    $this->actingAs(makeUser())->postJson("/api/v1/learn/{$course->slug}/questions", ['title' => 'Is Table 8A auto-filled?', 'body' => 'Where does 8A come from?'])->assertForbidden();
    $qid = $this->actingAs($learner)->postJson("/api/v1/learn/{$course->slug}/questions", ['title' => 'Is Table 8A auto-filled?', 'body' => 'Where does the figure in 8A come from?', 'lecture_id' => $lecture->id])
        ->assertCreated()->assertJsonPath('data.lecture.id', $lecture->id)->json('data.id');
    Notification::assertSentTo($course->instructor, NewQuestion::class);

    $this->actingAs($course->instructor)->getJson('/api/v1/instructor/questions?unanswered=1')->assertJsonCount(1, 'data');
    $this->actingAs($course->instructor)->postJson("/api/v1/questions/{$qid}/answers", ['body' => 'From GSTR-2B for the year; reconcile it with Table 6.'])
        ->assertOk()
        ->assertJsonPath('data.instructor_answered', true)
        ->assertJsonPath('data.answers.0.is_instructor', true);
    Notification::assertSentTo($learner, NewAnswer::class);
    $this->actingAs($course->instructor)->getJson('/api/v1/instructor/questions?unanswered=1')->assertJsonCount(0, 'data');

    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}/questions?lecture={$lecture->id}")->assertJsonCount(1, 'data');
});

it('lets only the instructor or moderators hide Q&A posts', function () {
    $course = liveCourse();
    $learner = makeUser();
    enrollIn($course, $learner);
    $qid = $this->actingAs($learner)->postJson("/api/v1/learn/{$course->slug}/questions", ['title' => 'Off-topic question here', 'body' => 'Not about this course at all.'])->json('data.id');

    $this->actingAs($learner)->postJson("/api/v1/questions/{$qid}/hide")->assertForbidden();
    $this->actingAs($course->instructor)->postJson("/api/v1/questions/{$qid}/hide")->assertNoContent();

    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}/questions")->assertJsonCount(0, 'data');
    $this->actingAs($learner)->getJson("/api/v1/questions/{$qid}")->assertNotFound();
    $this->actingAs($course->instructor)->getJson("/api/v1/learn/{$course->slug}/questions")->assertJsonCount(1, 'data');
    $this->actingAs(makeStaff(Permission::ModerateCourses))->postJson("/api/v1/questions/{$qid}/hide", ['hidden' => false])->assertNoContent();
});

it('sends announcements to active learners who want them, within limits', function () {
    $course = liveCourse();
    $keen = makeUser();
    $quiet = makeUser();
    enrollIn($course, $keen);
    enrollIn($course, $quiet);
    NotificationPreference::for($quiet->id)->fill(['course_announcements' => false])->save();

    $this->actingAs(makeUser())->postJson("/api/v1/instructor/courses/{$course->uuid}/announcements", ['title' => 'New lecture', 'body' => '<p>Hello</p>'])->assertForbidden();
    $this->actingAs(liveCourse()->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/announcements", ['title' => 'New lecture', 'body' => '<p>Hello everyone, new lecture added.</p>'])->assertNotFound();

    $body = '<p>I have added a lecture on <strong>Table 17</strong>.</p><script>alert(1)</script>';
    $this->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/announcements", ['title' => 'New lecture on Table 17', 'body' => $body])->assertCreated();
    expect(Announcement::query()->value('body_html'))->not->toContain('<script>');

    Queue::assertPushed(SendAnnouncement::class);
    (new SendAnnouncement(Announcement::query()->value('id')))->handle();
    Notification::assertSentTo($keen, CourseAnnouncement::class, fn ($n, $channels) => $channels === ['database', 'mail']);
    Notification::assertNotSentTo($quiet, CourseAnnouncement::class); // via() returned no channels
    expect(Announcement::query()->value('recipients_count'))->toBe(2);

    $this->actingAs($keen)->getJson("/api/v1/learn/{$course->slug}/announcements")->assertJsonCount(1, 'data');

    foreach (range(1, 3) as $i) {
        $this->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/announcements", ['title' => "Update number {$i}", 'body' => '<p>Another useful update for everyone here.</p>'])->assertCreated();
    }
    $this->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/announcements", ['title' => 'One too many', 'body' => '<p>Another useful update for everyone here.</p>'])
        ->assertStatus(429)->assertJsonPath('error.code', 'ANNOUNCEMENT_LIMIT');
});

it('keeps an in-app inbox and respects email preferences', function () {
    $user = makeUser();
    $user->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => 'test',
        'data' => ['kind' => 'qa_replies', 'title' => 'Hello', 'body' => 'World', 'url' => '/my-learning'],
    ]);

    $res = $this->actingAs($user)->getJson('/api/v1/me/notifications')->assertJsonPath('data.unread', 1)->assertJsonPath('data.items.0.title', 'Hello');
    $this->actingAs($user)->postJson('/api/v1/me/notifications/read-all')->assertNoContent();
    $this->actingAs($user)->getJson('/api/v1/me/notifications')->assertJsonPath('data.unread', 0)->assertJsonPath('data.items.0.read', true);
    expect($res->json('data.items.0.id'))->toBeString();

    $this->actingAs($user)->getJson('/api/v1/me/notification-preferences')->assertJsonPath('data.promotions', false)->assertJsonPath('data.qa_replies', true);
    $this->actingAs($user)->patchJson('/api/v1/me/notification-preferences', ['qa_replies' => false])->assertJsonPath('data.qa_replies', false);
});

it('renders branded emails and escapes what learners write', function () {
    $course = liveCourse();
    $learner = named('Mallory');
    enrollIn($course, $learner);
    $question = Question::query()->create([
        'course_id' => $course->id, 'user_id' => $learner->id, 'title' => '[Click here](https://evil.example)', 'body' => 'Please **help**', 'last_activity_at' => now(),
    ]);

    $html = (string) (new NewQuestion($question))->toMail($course->instructor)->render();
    expect($html)->toContain('/brand/erudsway-logo.png')
        ->toContain('Erudsway Educorp LLP')
        ->not->toContain('href="https://evil.example"')
        ->not->toContain('<strong>help</strong>');
});

function named(string $name): User
{
    $user = makeUser();
    $user->forceFill(['name' => $name])->save();

    return $user;
}
