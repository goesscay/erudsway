<?php

use App\Models\User;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Notifications\NewSubmission;
use App\Modules\Assessment\Notifications\SubmissionReviewed;
use App\Modules\Authoring\Services\CourseReadiness;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Certificates\Notifications\CertificateIssued;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

function addLecture(Course $course, string $kind, string $title = 'Check your understanding'): Lecture
{
    $section = $course->sections()->firstOrFail();

    return $section->lectures()->create(['course_id' => $course->id, 'title' => $title, 'kind' => $kind, 'position' => 99]);
}

/** Two questions: Q1 single-choice (B), Q2 multiple-choice (A and C). */
function quizPayload(int $pass = 50): array
{
    return [
        'pass_percent' => $pass,
        'questions' => [
            ['type' => 'single', 'prompt' => 'Which return reports outward supplies?', 'explanation' => 'GSTR-1 lists outward supplies.', 'options' => [
                ['body' => 'GSTR-3B', 'is_correct' => false], ['body' => 'GSTR-1', 'is_correct' => true], ['body' => 'GSTR-9', 'is_correct' => false],
            ]],
            ['type' => 'multiple', 'prompt' => 'Which are annual returns?', 'options' => [
                ['body' => 'GSTR-9', 'is_correct' => true], ['body' => 'GSTR-3B', 'is_correct' => false], ['body' => 'GSTR-9C', 'is_correct' => true],
            ]],
        ],
    ];
}

/** @return array{0: Lecture, 1: array<int, array{id: int, options: list<int>}>} lecture, and question index => ids */
function builtQuiz(Course $course, int $pass = 50): array
{
    $lecture = addLecture($course, 'quiz');
    $res = test()->actingAs($course->instructor)->putJson("/api/v1/instructor/lectures/{$lecture->id}/quiz", quizPayload($pass))->assertOk();
    $ids = array_map(fn ($q) => ['id' => $q['id'], 'options' => array_column($q['options'], 'id')], $res->json('data.questions'));

    return [$lecture->fresh(), $ids];
}

it('lets the instructor build a quiz, keeping ids stable across edits', function () {
    $course = liveCourse();
    $lecture = addLecture($course, 'quiz');
    $url = "/api/v1/instructor/lectures/{$lecture->id}/quiz";

    $this->actingAs(liveCourse()->instructor)->putJson($url, quizPayload())->assertNotFound();
    $bad = quizPayload();
    $bad['questions'][0]['options'][0]['is_correct'] = true; // two correct on a single-choice question
    $this->actingAs($course->instructor)->putJson($url, $bad)->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_QUIZ');

    $first = $this->actingAs($course->instructor)->putJson($url, quizPayload())->assertOk()->json('data');
    expect($first['questions'])->toHaveCount(2)
        ->and($lecture->fresh()->duration_seconds)->toBe(120);

    // Edit: keep Q1 (reworded), drop Q2, add a new question.
    $edit = quizPayload();
    $edit['questions'][0]['id'] = $first['questions'][0]['id'];
    $edit['questions'][0]['prompt'] = 'Which return lists outward supplies?';
    foreach ($first['questions'][0]['options'] as $i => $o) {
        $edit['questions'][0]['options'][$i]['id'] = $o['id'];
    }
    $edit['questions'][1] = ['type' => 'single', 'prompt' => 'Is GSTR-3B monthly?', 'options' => [['body' => 'Yes', 'is_correct' => true], ['body' => 'No', 'is_correct' => false]]];
    $second = $this->actingAs($course->instructor)->putJson($url, $edit)->json('data');

    expect($second['questions'][0]['id'])->toBe($first['questions'][0]['id'])
        ->and(array_column($second['questions'][0]['options'], 'id'))->toBe(array_column($first['questions'][0]['options'], 'id'))
        ->and($second['questions'][1]['id'])->not->toBe($first['questions'][1]['id']);
});

it('grades quizzes, completes the lecture on a pass, and keeps the instructor’s tries unrecorded', function () {
    $course = liveCourse();
    [$lecture, $q] = builtQuiz($course, pass: 100);
    $learner = makeUser();
    enrollIn($course, $learner);

    $this->actingAs(makeUser())->getJson("/api/v1/learn/lectures/{$lecture->id}/quiz")->assertForbidden();
    $quiz = $this->actingAs($learner)->getJson("/api/v1/learn/lectures/{$lecture->id}/quiz")->assertOk()->json('data');
    expect($quiz['questions'][0]['options'][0])->toHaveKeys(['id', 'body'])->not->toHaveKey('is_correct')
        ->and($quiz['last_result'])->toBeNull();

    $url = "/api/v1/learn/lectures/{$lecture->id}/quiz/attempts";
    // Q1 right; Q2 only half right, which is wrong.
    $half = ['answers' => [
        ['question_id' => $q[0]['id'], 'option_ids' => [$q[0]['options'][1]]],
        ['question_id' => $q[1]['id'], 'option_ids' => [$q[1]['options'][0]]],
    ]];
    $this->actingAs($learner)->postJson($url, $half)->assertOk()
        ->assertJsonPath('data.score_percent', 50)->assertJsonPath('data.passed', false)
        ->assertJsonPath('data.review.0.is_correct', true)->assertJsonPath('data.review.1.is_correct', false)
        ->assertJsonPath('data.review.0.explanation', 'GSTR-1 lists outward supplies.');
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")->assertJsonPath('data.enrollment.completed_count', 0);

    // Two answers on a single-choice question, or someone else's option, are refused.
    $this->actingAs($learner)->postJson($url, ['answers' => [['question_id' => $q[0]['id'], 'option_ids' => $q[0]['options']]]])->assertUnprocessable();
    $this->actingAs($learner)->postJson($url, ['answers' => [['question_id' => $q[0]['id'], 'option_ids' => [$q[1]['options'][0]]]]])->assertUnprocessable();

    $right = ['answers' => [
        ['question_id' => $q[0]['id'], 'option_ids' => [$q[0]['options'][1]]],
        ['question_id' => $q[1]['id'], 'option_ids' => [$q[1]['options'][2], $q[1]['options'][0]]],
    ]];
    $this->actingAs($learner)->postJson($url, $right)->assertJsonPath('data.score_percent', 100)->assertJsonPath('data.passed', true);
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")->assertJsonPath('data.enrollment.completed_count', 1);

    // A later fail doesn't undo the pass, and quizzes can't be ticked off by hand.
    $this->actingAs($learner)->postJson($url, $half)->assertJsonPath('data.passed', false);
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")->assertJsonPath('data.enrollment.completed_count', 1);
    $this->actingAs($learner)->deleteJson("/api/v1/lectures/{$lecture->id}/completion")->assertJsonPath('error.code', 'COMPLETES_AUTOMATICALLY');
    $this->actingAs($learner)->getJson("/api/v1/learn/lectures/{$lecture->id}/quiz")
        ->assertJsonPath('data.attempts.count', 3)->assertJsonPath('data.attempts.best_percent', 100)->assertJsonPath('data.attempts.passed', true);

    $this->actingAs($course->instructor)->postJson($url, $right)->assertJsonPath('data.recorded', false);
    expect(QuizAttempt::query()->count())->toBe(3);
});

it('runs assignments: brief, hand-in with a file, feedback', function () {
    $course = liveCourse();
    $lecture = addLecture($course, 'assignment', 'Reconcile GSTR-2B with your books');
    $this->actingAs($course->instructor)->putJson("/api/v1/instructor/lectures/{$lecture->id}/assignment", [
        'instructions_html' => '<p>Download the workbook and reconcile it. Upload your sheet.</p><script>x()</script>',
        'allow_file' => true,
    ])->assertOk()->assertJsonPath('data.instructions_html', '<p>Download the workbook and reconcile it. Upload your sheet.</p>');

    $learner = makeUser();
    enrollIn($course, $learner);
    $url = "/api/v1/learn/lectures/{$lecture->id}/assignment";
    $this->actingAs($learner)->post($url, [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('error.code', 'EMPTY_SUBMISSION');
    $this->actingAs($learner)->post($url, ['file' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])->assertUnprocessable();

    $id = $this->actingAs($learner)->post($url, [
        'body' => 'Differences are in Table 8A; see sheet.',
        'file' => UploadedFile::fake()->create('recon.xlsx', 40),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.file.name', 'recon.xlsx')->json('data.id');
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")->assertJsonPath('data.enrollment.completed_count', 1);
    Notification::assertSentTo($course->instructor, NewSubmission::class);

    $this->actingAs($learner)->get("/api/v1/assignment-submissions/{$id}/file")->assertOk();
    $this->actingAs(tap(makeUser(), fn (User $u) => enrollIn($course, $u)))->get("/api/v1/assignment-submissions/{$id}/file")->assertNotFound();
    $this->actingAs($course->instructor)->get("/api/v1/assignment-submissions/{$id}/file")->assertOk();

    $this->actingAs($course->instructor)->getJson('/api/v1/instructor/submissions')->assertJsonCount(1, 'data')->assertJsonPath('data.0.learner.name', $learner->name);
    $this->actingAs(liveCourse()->instructor)->putJson("/api/v1/instructor/submissions/{$id}/review", ['feedback' => 'Good'])->assertNotFound();
    $this->actingAs($course->instructor)->putJson("/api/v1/instructor/submissions/{$id}/review", ['feedback' => 'Well reconciled. Check the RCM line.'])
        ->assertJsonPath('data.status', 'reviewed');
    Notification::assertSentTo($learner, SubmissionReviewed::class);
    $this->actingAs($course->instructor)->getJson('/api/v1/instructor/submissions')->assertJsonCount(0, 'data');

    $this->actingAs($learner)->post($url, ['body' => 'Second go'], ['Accept' => 'application/json'])->assertStatus(409);
    $this->actingAs($learner)->getJson($url)->assertJsonPath('data.submission.feedback', 'Well reconciled. Check the RCM line.');
});

it('needs every quiz and assignment filled in before a course can be submitted', function () {
    $course = readyCourse();
    $readiness = app(CourseReadiness::class);
    expect($readiness->isReady($course))->toBeTrue();

    $quiz = addLecture($course, 'quiz');
    expect($readiness->isReady($course->fresh()))->toBeFalse();
    $this->actingAs($course->instructor)->putJson("/api/v1/instructor/lectures/{$quiz->id}/quiz", quizPayload())->assertOk();
    expect($readiness->isReady($course->fresh()))->toBeTrue();

    addLecture($course, 'assignment');
    expect(collect($readiness->check($course->fresh()))->firstWhere('key', 'assignments')['ok'])->toBeFalse();
});

it('issues a certificate when the last lecture is done, verifiable by anyone', function () {
    $course = liveCourse();
    [$quizLecture, $q] = builtQuiz($course, pass: 0); // any attempt passes, as on the old site
    $learner = makeUser();
    $learner->forceFill(['name' => 'Priya Raman'])->save();
    $enrollment = enrollIn($course, $learner);

    foreach ($course->lectures()->where('kind', 'video')->get() as $lecture) {
        $this->actingAs($learner)->postJson("/api/v1/lectures/{$lecture->id}/completion")->assertOk();
    }
    expect(Certificate::query()->count())->toBe(0);

    $this->actingAs($learner)->postJson("/api/v1/learn/lectures/{$quizLecture->id}/quiz/attempts", ['answers' => []])->assertJsonPath('data.passed', true);
    $certificate = Certificate::query()->sole();
    expect($certificate)->recipient_name->toBe('Priya Raman')
        ->course_title->toBe($course->title)
        ->serial->toMatch('/^ERW-C-\d{4}-\d{5}$/')
        ->code->toMatch('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/');
    Notification::assertSentTo($learner, CertificateIssued::class);

    // Un-ticking and re-completing doesn't issue a second one.
    $video = $course->lectures()->where('kind', 'video')->firstOrFail();
    $this->actingAs($learner)->deleteJson("/api/v1/lectures/{$video->id}/completion");
    $this->actingAs($learner)->postJson("/api/v1/lectures/{$video->id}/completion");
    expect(Certificate::query()->count())->toBe(1);

    $this->actingAs($learner)->getJson('/api/v1/me/certificates')->assertJsonPath('data.0.code', $certificate->code);
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")->assertJsonPath('data.enrollment.certificate_code', $certificate->code);
    $this->actingAs($learner)->getJson('/api/v1/me/enrollments')->assertJsonPath('data.0.certificate_code', $certificate->code);

    auth()->forgetGuards();
    $this->getJson('/api/v1/certificates/'.strtolower($certificate->code))->assertOk()
        ->assertJsonPath('data.recipient_name', 'Priya Raman')->assertJsonPath('data.valid', true);
    $pdf = $this->get("/api/v1/certificates/{$certificate->code}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->getJson('/api/v1/certificates/AAAA-BBBB-CCCC')->assertNotFound();

    // Access ending (refund or revoke) invalidates it.
    $enrollment->forceFill(['status' => 'refunded', 'revoked_at' => now()])->save();
    $this->getJson("/api/v1/certificates/{$certificate->code}")->assertJsonPath('data.valid', false);
    $this->get("/api/v1/certificates/{$certificate->code}/pdf")->assertNotFound();
});

it('backfills certificates for enrollments completed before', function () {
    $course = liveCourse();
    $enrollment = enrollIn($course);
    Enrollment::query()->whereKey($enrollment->id)->update(['completed_at' => now()->subMonth()]);

    $this->artisan('certificates:issue-missing')->expectsOutput('Issued 1 certificate(s).')->assertSuccessful();
    $this->artisan('certificates:issue-missing')->expectsOutput('Issued 0 certificate(s).');
    expect(Certificate::query()->sole()->issued_at->isSameDay(now()->subMonth()))->toBeTrue();
});
