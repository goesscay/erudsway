<?php

use App\Models\User;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Identity\Enums\Role;
use App\Modules\Learning\Enums\EnrollmentSource;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Legacy\Models\LegacyLink;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Jobs\ProcessVideo;
use App\Modules\Media\Models\VideoAsset;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('local');
    Storage::fake('public');
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    $this->instructor = makeUser(Role::Instructor);
    $this->admin = makeUser(Role::Admin);
    $this->bundle = moodleBundle();
});

afterEach(function () {
    foreach (glob($this->bundle.'/files/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($this->bundle.'/files');
    @unlink($this->bundle.'/manifest.json');
    @rmdir($this->bundle);
});

/** A small export bundle shaped like deploy/server/bin/moodle-export's output. */
function moodleBundle(): string
{
    $dir = sys_get_temp_dir().'/moodle-bundle-'.bin2hex(random_bytes(4));
    mkdir("{$dir}/files", 0777, true);
    $put = function (string $bytes) use ($dir): string {
        $hash = sha1($bytes);
        file_put_contents("{$dir}/files/{$hash}", $bytes);

        return $hash;
    };
    $file = fn (string $area, string $name, string $hash, string $mime) => ['area' => $area, 'name' => $name, 'hash' => $hash, 'mime' => $mime, 'size' => filesize("{$dir}/files/{$hash}")];

    $image = function (int $w, int $h, string $type): string {
        $gd = imagecreatetruecolor($w, $h);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 10, 40, 90));
        ob_start();
        $type === 'jpeg' ? imagejpeg($gd) : imagepng($gd);

        return (string) ob_get_clean();
    };
    $cover = $put($image(800, 450, 'jpeg'));
    $png = $put($image(40, 30, 'png'));
    $pdf = $put('%PDF-1.4 companion');
    $mp4 = $put(str_repeat('v', 2048));
    $long = '<p>'.str_repeat('GST becomes easier when you understand the business transaction first. ', 20).'</p>';
    $notes = '<h2>GST Registration</h2><p>Your goal: prepare a correct application.</p><p><img class="img-fluid" src="@@PLUGINFILE@@/image.png"></p>'
        .'<table><tbody><tr><th>Word</th><th>Meaning</th></tr><tr><td>TRN</td><td>Temporary Reference Number</td></tr></tbody></table>'
        .'<p>'.str_repeat('Keep these details beside you while watching. ', 8).'</p><script>alert(1)</script><img src="https://evil.example/x.png">';

    $manifest = [
        'format' => 'erudsway-moodle-export',
        'version' => 1,
        'source' => ['site' => 'https://erudsway.com', 'course_id' => 206, 'exported_at' => '2026-10-19T12:00:00+00:00'],
        'course' => [
            'fullname' => 'GST Practical Mastery | Registration to GSTR-9C',
            'shortname' => 'ria-gst-pm-14',
            'summary_html' => '<p>Master the complete GST compliance journey through practical working papers.</p>',
            'overview_files' => [$file('overviewfiles', 'cover.jpg', $cover, 'image/jpeg')],
        ],
        'sections' => [
            ['number' => 0, 'name' => '', 'visible' => true, 'modules' => [
                ['cmid' => 1621, 'type' => 'page', 'visible' => true, 'name' => 'About this course', 'html' => '<p>'.str_repeat('Welcome to the course. ', 20).'</p>',
                    'files' => [$file('content', 'Companion.pdf', $pdf, 'application/pdf')]],
                ['cmid' => 1637, 'type' => 'label', 'visible' => true, 'name' => 'GST Practical Mastery…', 'html' => $long, 'files' => []],
                ['cmid' => 1638, 'type' => 'label', 'visible' => true, 'name' => 'Start Here', 'html' => '<h3>Start Here</h3>', 'files' => []],
                ['cmid' => 1640, 'type' => 'label', 'visible' => true, 'name' => 'Text and media area', 'html' => '<p><img src="@@PLUGINFILE@@/journey.png"></p>',
                    'files' => [$file('intro', 'journey.png', $png, 'image/png')]],
            ]],
            ['number' => 1, 'name' => '01 &middot; GST Registration: Documents and Verification', 'visible' => true, 'modules' => [
                ['cmid' => 1623, 'type' => 'page', 'visible' => true, 'name' => '▶ GST Registration: Documents and Verification',
                    'html' => '<div class="ri-lesson-video"><video controls="controls"><source src="@@PLUGINFILE@@/GST%20Registration.mp4" type="video/mp4"></video></div>'.$notes,
                    'files' => [$file('content', 'GST Registration.mp4', $mp4, 'video/mp4'), $file('content', 'image.png', $png, 'image/png')]],
                ['cmid' => 1645, 'type' => 'quiz', 'visible' => true, 'name' => 'Check your understanding', 'html' => null, 'files' => [], 'quiz' => ['questions' => [
                    ['qtype' => 'multichoice', 'single' => true, 'text_html' => '<p>Consultant · Basic</p><p>When does registration liability arise?</p>', 'feedback_html' => '<p>Under section 22.</p>', 'answers' => [
                        ['text_html' => '<p>Above ₹40 lakh</p>', 'fraction' => 0.0, 'feedback_html' => ''],
                        ['text_html' => '<p>Above ₹20 lakh</p>', 'fraction' => 1.0, 'feedback_html' => ''],
                    ]],
                    ['qtype' => 'essay', 'single' => true, 'text_html' => '<p>Explain.</p>', 'feedback_html' => '', 'answers' => []],
                ]]],
            ]],
            ['number' => 2, 'name' => 'GST Practice Vault', 'visible' => true, 'modules' => [
                ['cmid' => 1642, 'type' => 'page', 'visible' => true, 'name' => 'What is in GST Practice Vault', 'html' => '<p>Edition extras.</p>', 'files' => []],
            ]],
            ['number' => 3, 'name' => 'Certificate', 'visible' => true, 'modules' => [
                ['cmid' => 1636, 'type' => 'customcert', 'visible' => true, 'name' => 'customcert', 'html' => null, 'files' => []],
            ]],
        ],
        'users' => [
            moodleUser(10, 'Gopi@Example.com', [1621, 1623, 1645], ['code' => 'AbCdEf1234', 'issued_at' => '2026-10-04T14:40:36+00:00']),
            moodleUser(11, 'partial@example.com', [1623]),
            [...moodleUser(20, 'teacher@example.com', []), 'roles' => ['editingteacher']],
        ],
    ];
    file_put_contents("{$dir}/manifest.json", json_encode($manifest));

    return $dir;
}

/** @param list<int> $completed */
function moodleUser(int $id, string $email, array $completed, ?array $certificate = null): array
{
    return [
        'id' => $id, 'email' => $email, 'firstname' => 'Learner', 'lastname' => (string) $id,
        'password_hash' => crypt('moodle-secret-'.$id, '$6$rounds=5000$saltsalt$'),
        'auth' => 'email', 'confirmed' => true, 'suspended' => false, 'deleted' => false, 'roles' => ['student'], 'active' => true,
        'created_at' => '2026-09-30T10:00:00+00:00', 'enrolled_at' => '2026-10-01T01:57:21+00:00',
        'edition' => ['tier' => 1, 'name' => 'Video Course', 'amount' => '7800.00', 'granted_at' => null],
        'payments' => [['amount' => '7800', 'currency' => 'INR', 'gateway' => 'razorpay', 'paid_at' => '2026-10-01T01:57:21+00:00',
            'razorpay_payment_id' => "pay_TEST{$id}", 'razorpay_order_id' => "order_TEST{$id}", 'item' => 'GST Practical Mastery']],
        'completed_cmids' => $completed,
        'certificate' => $certificate,
    ];
}

function importCourse(): void
{
    test()->artisan('moodle:import-course', [
        'bundle' => test()->bundle, '--instructor' => test()->instructor->email, '--skip-sections' => '2',
    ])->assertSuccessful();
}

it('imports the course as a draft with videos, notes, quizzes and files', function () {
    importCourse();

    $course = Course::query()->sole();
    expect($course->title)->toBe('GST Practical Mastery: Registration to GSTR-9C')
        ->and($course->status)->toBe(CourseStatus::Draft)
        ->and($course->instructor_id)->toBe($this->instructor->id)
        ->and($course->priceTier->amount)->toBe(780000)
        ->and($course->pricing_confirmed_at)->not->toBeNull()
        ->and($course->thumbnail_path)->not->toBeNull()
        ->and($course->subtitle)->toContain('Master the complete GST compliance journey')
        ->and(HtmlSanitizer::textLength($course->description))->toBeGreaterThan(1000);

    // Section 2 skipped on request; section 3 held only a certificate activity.
    $sections = $course->sections()->with('lectures')->get();
    expect($sections->pluck('title')->all())->toBe(['Start here', 'GST Registration: Documents and Verification']);
    expect($sections[0]->lectures->pluck('title')->all())->toBe(['About this course'])
        ->and($sections[1]->lectures->map(fn ($l) => [$l->kind, $l->title])->all())->toBe([
            [LectureKind::Video, 'GST Registration: Documents and Verification'],
            [LectureKind::Article, 'Lesson notes'],
            [LectureKind::Quiz, 'Check your understanding'],
        ]);

    // The image-only label joined the "About" reading; the PDF became a download.
    $about = $sections[0]->lectures[0];
    expect($about->article_html)->toContain('<img src="/storage/lecture-images/')
        ->and($about->resources()->pluck('original_name')->all())->toBe(['Companion.pdf']);

    $notes = $sections[1]->lectures[1];
    expect($notes->article_html)
        ->toContain('<table>')->toContain('<h3>GST Registration</h3>')->toContain('src="/storage/lecture-images/')
        ->not->toContain('<video')->not->toContain('<script')->not->toContain('evil.example');
    preg_match('#src="/storage/([^"]+)"#', $notes->article_html, $m);
    Storage::disk('public')->assertExists($m[1]);

    $quiz = Quiz::query()->where('lecture_id', $sections[1]->lectures[2]->id)->with('questions.options')->sole();
    expect($quiz->questions)->toHaveCount(1)
        ->and($quiz->questions[0]->prompt)->toBe("Consultant · Basic\nWhen does registration liability arise?")
        ->and($quiz->questions[0]->explanation)->toBe('Under section 22.')
        ->and($quiz->questions[0]->options->where('is_correct', true)->pluck('body')->all())->toBe(['Above ₹20 lakh']);

    $video = VideoAsset::query()->sole();
    expect($video->status)->toBe(VideoStatus::Processing)->and($video->owner_id)->toBe($this->instructor->id);
    Storage::disk('local')->assertExists($video->source_path);
    expect($sections[1]->lectures[0]->video_asset_id)->toBe($video->id);
    Queue::assertPushed(ProcessVideo::class, 1);
});

it('resumes instead of duplicating when run again', function () {
    importCourse();
    importCourse();

    expect(Course::query()->count())->toBe(1)
        ->and(Course::query()->sole()->lectures()->count())->toBe(4)
        ->and(VideoAsset::query()->count())->toBe(1)
        ->and(PriceTier::query()->where('amount', 780000)->count())->toBe(1);
    // A video not transcoded yet is queued again; the job is unique, so it's still queued once.
    Queue::assertPushed(ProcessVideo::class, 1);

    // Once transcoded, a re-run leaves it alone.
    VideoAsset::query()->update(['status' => VideoStatus::Ready->value, 'processed_at' => now()]);
    $this->artisan('moodle:import-course', ['bundle' => $this->bundle, '--instructor' => $this->instructor->email, '--skip-sections' => '2'])
        ->doesntExpectOutputToContain('queued again')->assertSuccessful();
});

it('refuses an unknown instructor or a bundle that isn’t an export', function () {
    $this->artisan('moodle:import-course', ['bundle' => $this->bundle, '--instructor' => makeUser()->email])->assertFailed();
    $this->artisan('moodle:import-course', ['bundle' => sys_get_temp_dir(), '--instructor' => $this->instructor->email])->assertFailed();
    expect(Course::query()->count())->toBe(0);
});

it('brings learners across with access, progress and certificates once the course is live', function () {
    importCourse();
    $this->artisan('moodle:import-learners', ['bundle' => $this->bundle, '--admin' => $this->admin->email])->assertFailed();

    $course = Course::query()->sole();
    VideoAsset::query()->update(['status' => VideoStatus::Ready->value, 'duration_seconds' => 600]);
    $course->forceFill(['status' => CourseStatus::Published, 'published_at' => now()])->save();

    $this->artisan('moodle:import-learners', ['bundle' => $this->bundle, '--admin' => $this->admin->email])->assertSuccessful();

    $gopi = User::query()->where('email', 'gopi@example.com')->sole();
    expect($gopi->hasRole(Role::Student->value))->toBeTrue()
        ->and($gopi->email_verified_at)->not->toBeNull()
        ->and(DB::table('users')->where('id', $gopi->id)->value('legacy_password_hash'))->toStartWith('$6$')
        ->and(LegacyLink::target('user', 10))->toBe($gopi->id);

    $enrollment = Enrollment::query()->where('user_id', $gopi->id)->sole();
    expect($enrollment->source)->toBe(EnrollmentSource::AdminGrant)
        ->and($enrollment->reason)->toContain('Video Course edition, ₹7,800')->toContain('pay_TEST10')
        ->and($enrollment->granted_at->toIso8601String())->toBe('2026-10-01T01:57:21+00:00')
        ->and($enrollment->progress_percent)->toBe(100);
    $certificate = Certificate::query()->where('user_id', $gopi->id)->sole();
    expect($certificate->issued_at->toIso8601String())->toBe('2026-10-04T14:40:36+00:00');

    // Completed only the video page: the video and its notes count, the quiz doesn't.
    $partial = Enrollment::query()->whereHas('user', fn ($q) => $q->where('email', 'partial@example.com'))->sole();
    expect($partial->completed_count)->toBe(2)->and($partial->completed_at)->toBeNull();

    expect(User::query()->where('email', 'teacher@example.com')->exists())->toBeFalse();

    // Running again changes nothing.
    $this->artisan('moodle:import-learners', ['bundle' => $this->bundle, '--admin' => $this->admin->email])->assertSuccessful();
    expect(Enrollment::query()->count())->toBe(2)->and(Certificate::query()->count())->toBe(1);
});

it('signs moved learners in with their Moodle password once, then with a normal hash', function () {
    $user = makeUser();
    DB::table('users')->where('id', $user->id)->update(['legacy_password_hash' => crypt('moodle-secret', '$6$rounds=5000$saltsalt$')]);

    $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable();
    expect(DB::table('users')->where('id', $user->id)->value('legacy_password_hash'))->not->toBeNull();

    $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'moodle-secret'])->assertOk();
    $fresh = DB::table('users')->where('id', $user->id)->first();
    expect($fresh->legacy_password_hash)->toBeNull()
        ->and(Hash::check('moodle-secret', $fresh->password))->toBeTrue();
});

it('keeps tables and own-site images in reading lectures but nowhere else', function () {
    $html = '<h2>x</h2><table><tr><td>a</td></tr></table><img src="/storage/lecture-images/1/a.png"><img src="https://evil.example/t.png">';
    $sanitizer = app(HtmlSanitizer::class);

    expect($sanitizer->cleanArticle($html))
        ->toContain('<table>')->toContain('src="/storage/lecture-images/1/a.png"')->not->toContain('evil.example')
        ->and($sanitizer->clean($html))->not->toContain('<table>')->not->toContain('<img');
});
