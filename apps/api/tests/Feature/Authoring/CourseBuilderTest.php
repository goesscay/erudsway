<?php

use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;
use Database\Factories\CourseFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

describe('access', function () {
    it('is limited to instructors', function () {
        $this->actingAs(makeUser())->getJson('/api/v1/instructor/courses')->assertForbidden();
        $this->actingAs(makeUser(Role::Instructor))->getJson('/api/v1/instructor/courses')->assertOk();
    });

    it('hides other instructors\' courses', function () {
        $course = draftFor();

        $this->actingAs(CourseFactory::instructor())
            ->getJson("/api/v1/instructor/courses/{$course->uuid}")
            ->assertNotFound();
        $this->actingAs(CourseFactory::instructor())
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Hijacked title'])
            ->assertNotFound();
    });

    it('lists only my own courses', function () {
        $me = CourseFactory::instructor();
        draftFor($me);
        draftFor();

        $this->actingAs($me)->getJson('/api/v1/instructor/courses')->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('creating and editing', function () {
    it('creates a draft with a first section and a unique slug', function () {
        $instructor = CourseFactory::instructor();
        Course::factory()->create(['slug' => 'gst-for-beginners']);

        $this->actingAs($instructor)
            ->postJson('/api/v1/instructor/courses', ['title' => 'GST for Beginners', 'category' => 'registration-returns'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.curriculum.0.title', 'Introduction')
            ->assertJsonPath('data.pricing_confirmed', false);

        expect(Course::query()->where('instructor_id', $instructor->id)->value('slug'))
            ->toStartWith('gst-for-beginners-');
    });

    it('requires a subcategory, not a top-level category', function () {
        $this->actingAs(CourseFactory::instructor())
            ->postJson('/api/v1/instructor/courses', ['title' => 'GST for Beginners', 'category' => 'gst'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('category', 'error.fields');
    });

    it('sanitises the description', function () {
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", [
                'description' => '<p onclick="steal()">Hello <script>alert(1)</script><a href="javascript:evil()">x</a> <strong>world</strong></p>',
            ])
            ->assertOk();

        $html = $course->fresh()->description;
        expect($html)->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')
            ->and($html)->toContain('<strong>world</strong>');
    });

    it('cleans list fields and confirms pricing when a price is chosen', function () {
        $course = draftFor();
        $tier = PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => 59900]);

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", [
                'outcomes' => ['  File GSTR-1  ', '', 'Reconcile ITC'],
                'price_tier_id' => $tier->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.outcomes', ['File GSTR-1', 'Reconcile ITC'])
            ->assertJsonPath('data.price.amount', 59900)
            ->assertJsonPath('data.pricing_confirmed', true);
    });

    it('treats an explicit null price as choosing free', function () {
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['price_tier_id' => null])
            ->assertOk()
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.pricing_confirmed', true);
    });

    it('locks courses under review', function () {
        $course = draftFor();
        $course->forceFill(['status' => CourseStatus::InReview])->save();

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'A changed title'])
            ->assertForbidden()
            ->assertJsonPath('error.message', 'This course is being reviewed. You can edit it again once the review is complete.');
    });

    it('audits title and price changes on live courses', function () {
        $course = draftFor();
        $course->forceFill(['status' => CourseStatus::Published, 'published_at' => now()])->save();

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'A brand new live title'])
            ->assertOk();

        expect(AuditLog::query()->where('action', 'course.live_details_changed')->exists())->toBeTrue();
    });

    it('stores a re-encoded 16:9 course image', function () {
        Storage::fake('public');
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->post("/api/v1/instructor/courses/{$course->uuid}/thumbnail", [
                'image' => UploadedFile::fake()->image('cover.png', 1600, 1200),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $course->fresh()->thumbnail_path;
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        expect([$w, $h])->toBe([1280, 720]);
    });

    it('rejects images that are too small', function () {
        Storage::fake('public');
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->post("/api/v1/instructor/courses/{$course->uuid}/thumbnail", [
                'image' => UploadedFile::fake()->image('tiny.png', 400, 300),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'IMAGE_TOO_SMALL');
    });

    it('deletes drafts but not published courses', function () {
        $draft = draftFor();
        $this->actingAs($draft->instructor)->deleteJson("/api/v1/instructor/courses/{$draft->uuid}")->assertNoContent();
        expect(Course::withTrashed()->find($draft->id))->toBeNull();

        $live = draftFor();
        $live->forceFill(['status' => CourseStatus::Published, 'published_at' => now()])->save();
        $this->actingAs($live->instructor)->deleteJson("/api/v1/instructor/courses/{$live->uuid}")->assertForbidden();
    });
});

describe('curriculum', function () {
    it('adds sections and lectures and keeps course totals current', function () {
        $course = draftFor();
        $section = $course->sections()->first();

        $this->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/sections", ['title' => 'Returns'])
            ->assertOk()->assertJsonCount(2, 'data.curriculum');
        $this->actingAs($course->instructor)->postJson("/api/v1/instructor/sections/{$section->id}/lectures", ['title' => 'Welcome', 'kind' => 'video'])
            ->assertOk()->assertJsonPath('data.curriculum.0.lectures.0.title', 'Welcome');

        expect($course->fresh()->lecture_count)->toBe(1);
    });

    it('only allows video and article lectures for now', function () {
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->postJson("/api/v1/instructor/sections/{$course->sections()->first()->id}/lectures", ['title' => 'A quiz', 'kind' => 'quiz'])
            ->assertUnprocessable();
    });

    it('sanitises article content and estimates reading time', function () {
        $course = draftFor();
        $lecture = $course->sections()->first()->lectures()->create(['course_id' => $course->id, 'title' => 'Notes', 'kind' => 'article', 'position' => 1]);

        $this->actingAs($course->instructor)
            ->patchJson("/api/v1/instructor/lectures/{$lecture->id}", ['article_html' => '<p>'.str_repeat('word ', 400).'</p><script>x</script>'])
            ->assertOk();

        expect($lecture->fresh()->article_html)->not->toContain('script')
            ->and($lecture->fresh()->duration_seconds)->toBe(120);
    });

    it('reorders sections and moves lectures between them', function () {
        $course = draftFor();
        [$a] = [$course->sections()->first()];
        $b = $course->sections()->create(['title' => 'Second', 'position' => 2]);
        $l1 = $a->lectures()->create(['course_id' => $course->id, 'title' => 'One', 'kind' => 'video', 'position' => 1]);
        $l2 = $a->lectures()->create(['course_id' => $course->id, 'title' => 'Two', 'kind' => 'video', 'position' => 2]);

        $this->actingAs($course->instructor)
            ->putJson("/api/v1/instructor/courses/{$course->uuid}/curriculum/order", ['sections' => [
                ['id' => $b->id, 'lectures' => [$l2->id]],
                ['id' => $a->id, 'lectures' => [$l1->id]],
            ]])
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.title', 'Second')
            ->assertJsonPath('data.curriculum.0.lectures.0.title', 'Two');
    });

    it('rejects a reorder that does not match the current curriculum', function () {
        $course = draftFor();
        $other = draftFor();

        $this->actingAs($course->instructor)
            ->putJson("/api/v1/instructor/courses/{$course->uuid}/curriculum/order", ['sections' => [
                ['id' => $other->sections()->first()->id, 'lectures' => []],
            ]])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CURRICULUM_MISMATCH');
    });

    it('keeps at least one section', function () {
        $course = draftFor();

        $this->actingAs($course->instructor)
            ->deleteJson("/api/v1/instructor/sections/{$course->sections()->first()->id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'LAST_SECTION');
    });

    it('attaches downloadable resources to lectures', function () {
        Storage::fake('local');
        $course = draftFor();
        $lecture = $course->sections()->first()->lectures()->create(['course_id' => $course->id, 'title' => 'Templates', 'kind' => 'article', 'position' => 1]);

        $this->actingAs($course->instructor)
            ->post("/api/v1/instructor/lectures/{$lecture->id}/resources", [
                'file' => UploadedFile::fake()->create('GSTR-1 checklist.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.lectures.0.resources.0.name', 'GSTR-1 checklist.pdf');

        $this->actingAs($course->instructor)
            ->post("/api/v1/instructor/lectures/{$lecture->id}/resources", [
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    });
});

describe('submitting', function () {
    it('lists exactly what is missing', function () {
        $course = draftFor();

        $checklist = collect($this->actingAs($course->instructor)->getJson("/api/v1/instructor/courses/{$course->uuid}")->json('data.checklist'));

        expect($checklist->where('ok', false)->pluck('key')->all())
            ->toContain('thumbnail', 'lectures', 'video_minutes', 'pricing')
            ->and($checklist->firstWhere('key', 'category')['ok'])->toBeTrue();

        $this->actingAs($course->instructor)
            ->postJson("/api/v1/instructor/courses/{$course->uuid}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'COURSE_NOT_READY');
    });

    it('submits a complete course for review', function () {
        $course = readyCourse();

        $this->actingAs($course->instructor)
            ->postJson("/api/v1/instructor/courses/{$course->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.latest_submission.status', 'pending');
    });
});
