<?php

namespace App\Modules\Legacy\Moodle;

use App\Models\User;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Services\Quizzes;
use App\Modules\Authoring\Services\ThumbnailProcessor;
use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Legacy\Models\LegacyLink;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Jobs\ProcessVideo;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds a draft course from a Moodle export:
 *
 *   section 0 (unnamed)          → "Start here"
 *   page with an embedded video  → a video lecture, then a "Lesson notes" reading lecture
 *   page without a video         → a reading lecture
 *   label with real content      → a reading lecture (or, the first long one in section 0,
 *                                  the course description); image-only labels are added to
 *                                  the previous reading lecture; short headers are dropped
 *   quiz (multiple choice)       → a quiz lecture
 *   anything else (certificate…) → skipped; certificates are issued automatically here
 *
 * Other files on a page become downloadable resources; images are re-hosted. Videos are
 * moved out of the bundle into video storage and queued for transcoding. Each created record is linked to
 * its Moodle id, so running again resumes rather than duplicating.
 */
class CourseImporter
{
    private const SHORT_TEXT = 200;

    private const LONG_LABEL = 1000;

    /** @var list<string> */
    private array $log = [];

    private int $queued = 0;

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly Quizzes $quizzes,
        private readonly ThumbnailProcessor $thumbnails,
        private readonly CourseStats $stats,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $skipSections  Moodle section numbers to leave out
     * @return array{course: Course, log: list<string>, videos: int}
     */
    public function import(Bundle $bundle, User $instructor, Category $category, int $pricePaise, array $skipSections = []): array
    {
        $source = $bundle->manifest;
        $course = $this->course($bundle, $instructor, $category, $pricePaise);

        $position = (int) $course->sections()->max('position');
        foreach ($source['sections'] as $section) {
            if (in_array($section['number'], $skipSections, true) || ! $section['visible']) {
                $this->log[] = "Skipped section {$section['number']} ".($section['visible'] ? '(asked to)' : '(hidden in Moodle)').'.';

                continue;
            }
            $this->section($bundle, $course, $section, $position);
        }

        $this->stats->refresh($course);

        return ['course' => $course->refresh(), 'log' => $this->log, 'videos' => $this->queued];
    }

    private function course(Bundle $bundle, User $instructor, Category $category, int $pricePaise): Course
    {
        $moodleId = $bundle->courseId();
        if ($id = LegacyLink::target('course', $moodleId)) {
            $this->log[] = 'Course already imported; continuing with anything missing.';

            return Course::query()->findOrFail($id);
        }

        $source = $bundle->manifest['course'];
        $title = Str::limit(str_replace(' | ', ': ', html_entity_decode($source['fullname'], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 120, '');
        $tier = PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => $pricePaise], ['is_active' => true]);

        $course = DB::transaction(function () use ($instructor, $category, $title, $source, $tier, $moodleId) {
            $course = Course::query()->create([
                'instructor_id' => $instructor->id,
                'category_id' => $category->id,
                'price_tier_id' => $tier->id,
                'slug' => $this->uniqueSlug($title),
                'title' => $title,
                'subtitle' => Str::limit(MoodleHtml::toText($source['summary_html']), 197) ?: null,
                'level' => CourseLevel::All,
                'language' => 'English',
            ]);
            $course->forceFill(['pricing_confirmed_at' => now()])->save();
            LegacyLink::remember('course', $moodleId, $course);
            $this->audit->record('course.imported', $course, null, ['from' => 'moodle', 'moodle_course_id' => $moodleId]);

            return $course;
        });
        $this->log[] = "Created draft course “{$course->title}” ({$course->uuid}).";

        foreach ($source['overview_files'] as $file) {
            if (str_starts_with($file['mime'], 'image/')) {
                try {
                    $this->thumbnails->store($course, new UploadedFile($bundle->file($file['hash']), $file['name'], $file['mime'], null, true));
                    $this->log[] = 'Course image set from Moodle.';
                } catch (DomainException $e) {
                    $this->log[] = "Course image not used: {$e->getMessage()}";
                }
                break;
            }
        }

        return $course;
    }

    /** @param array{number: int, name: string, visible: bool, modules: list<array<string, mixed>>} $section */
    private function section(Bundle $bundle, Course $course, array $section, int &$position): void
    {
        $sectionId = LegacyLink::target('section', $section['number']);
        $model = $sectionId ? CourseSection::query()->find($sectionId) : null;
        if (! $model) {
            $model = $course->sections()->create(['title' => $this->sectionTitle($section), 'position' => ++$position]);
            LegacyLink::remember('section', $section['number'], $model);
        }

        $previousArticle = null;
        foreach ($section['modules'] as $module) {
            if (! $module['visible']) {
                $this->log[] = "Skipped hidden activity {$module['cmid']} ({$module['type']}).";

                continue;
            }
            if ($module['type'] === 'quiz') {
                $this->quiz($course, $model, $module);

                continue;
            }
            $previousArticle = match ($module['type']) {
                'page' => $this->page($bundle, $course, $model, $module) ?? $previousArticle,
                'label' => $this->label($bundle, $course, $model, $section['number'], $module, $previousArticle) ?? $previousArticle,
                default => $this->skip($module, $previousArticle),
            };
        }

        // A section that ended up empty (e.g. only a certificate activity) isn't useful.
        if (! $model->lectures()->exists()) {
            LegacyLink::query()->where(['system' => 'moodle', 'kind' => 'section', 'legacy_id' => (string) $section['number']])->delete();
            $model->delete();
            $position--;
        }
    }

    /** @param array<string, mixed> $module */
    private function page(Bundle $bundle, Course $course, CourseSection $section, array $module): ?Lecture
    {
        $cmid = (int) $module['cmid'];
        $html = (string) $module['html'];
        $title = $this->clean((string) $module['name']);
        /** @var list<array{area: string, name: string, hash: string, mime: string, size: int}> $files */
        $files = $module['files'];
        $videos = MoodleHtml::videoFiles($html);
        $videoFile = $videos ? collect($files)->firstWhere('name', $videos[0]) : null;

        $main = null;
        if ($videoFile !== null) {
            $main = $this->lecture($course, $section, $cmid, 'video', LectureKind::Video, preg_replace('/^[\x{25B6}\x{FE0F}\s]+/u', '', $title) ?? $title);
            $this->video($bundle, $course, $main, $videoFile);
        }

        $notes = null;
        $article = $this->article($bundle, $html, $files, $course);
        if (HtmlSanitizer::textLength($article) >= self::SHORT_TEXT || $main === null) {
            $notes = $this->lecture($course, $section, $cmid, $main ? 'notes' : 'article', LectureKind::Article, $main ? 'Lesson notes' : $title, $article);
        }

        // Everything that isn't the video or an inline image is offered as a download.
        $attachTo = $main ?? $notes;
        foreach ($files as $file) {
            if (! str_starts_with($file['mime'], 'image/') && ! str_starts_with($file['mime'], 'video/')) {
                $this->resource($bundle, $attachTo, $file);
            }
        }

        return $notes;
    }

    /** @param array<string, mixed> $module */
    private function label(Bundle $bundle, Course $course, CourseSection $section, int $sectionNumber, array $module, ?Lecture $previous): ?Lecture
    {
        $html = (string) $module['html'];
        /** @var list<array{area: string, name: string, hash: string, mime: string, size: int}> $files */
        $files = $module['files'];
        $length = HtmlSanitizer::textLength($html);
        $cmid = (int) $module['cmid'];

        if (LegacyLink::target('module', $cmid, 'description')) {
            return null;
        }
        if ($length >= self::LONG_LABEL) {
            // The first long label at the top of the course is its "About" text.
            if ($sectionNumber === 0 && $course->description === null) {
                $course->forceFill(['description' => $this->sanitizer->clean($html)])->save();
                LegacyLink::remember('module', $cmid, $course, 'description');
                $this->log[] = "Activity {$cmid}: used as the course description.";

                return null;
            }
            // Moodle names a label after its first ~50 characters, so prefer a heading or the first line.
            $firstLine = Str::before(MoodleHtml::toText($html), "\n");
            $title = MoodleHtml::firstHeading($html)
                ?? (mb_strlen($firstLine) <= 120 ? $firstLine : Str::before($this->clean((string) $module['name']), '...'));

            return $this->lecture($course, $section, $cmid, 'article', LectureKind::Article, $title, $this->article($bundle, $html, $files, $course));
        }

        if (LegacyLink::target('module', $cmid, 'appended')) {
            return null;
        }
        $hasImage = collect($files)->contains(fn (array $f) => str_starts_with($f['mime'], 'image/'));
        if ($hasImage && $previous !== null) {
            $extra = (string) $this->article($bundle, $html, $files, $course);
            $previous->forceFill(['article_html' => $this->sanitizer->cleanArticle($previous->article_html.$extra)])->save();
            LegacyLink::remember('module', $cmid, $previous, 'appended');
            $this->log[] = "Activity {$cmid}: image added to “{$previous->title}”.";

            return null;
        }
        if ($length > 0 || $hasImage) {
            $this->log[] = "Activity {$cmid}: short label “".Str::limit(MoodleHtml::toText($html) ?: $this->clean((string) $module['name']), 60).'” left out.';
        }

        return null;
    }

    /** @param array<string, mixed> $module */
    private function quiz(Course $course, CourseSection $section, array $module): void
    {
        $cmid = (int) $module['cmid'];
        if (LegacyLink::target('module', $cmid, 'quiz')) {
            return;
        }
        /** @var list<array{qtype: string, single: bool, text_html: string, feedback_html: string|null, answers: list<array{text_html: string, fraction: float, feedback_html: string|null}>}> $questions */
        $questions = $module['quiz']['questions'] ?? [];
        $data = [];
        foreach ($questions as $i => $q) {
            if ($q['qtype'] !== 'multichoice') {
                $this->log[] = "Activity {$cmid}: question ".($i + 1)." is a {$q['qtype']} question and was left out.";

                continue;
            }
            $data[] = [
                'type' => $q['single'] ? QuizQuestion::SINGLE : QuizQuestion::MULTIPLE,
                'prompt' => MoodleHtml::toText($q['text_html']),
                'explanation' => MoodleHtml::toText($q['feedback_html']) ?: null,
                'options' => array_map(fn (array $a) => [
                    'body' => MoodleHtml::toText($a['text_html']),
                    'is_correct' => $q['single'] ? $a['fraction'] >= 0.999 : $a['fraction'] > 0,
                ], $q['answers']),
            ];
        }
        if ($data === []) {
            $this->log[] = "Activity {$cmid}: quiz has no multiple-choice questions; left out.";

            return;
        }

        $lecture = $this->lecture($course, $section, $cmid, 'quiz', LectureKind::Quiz, $this->clean((string) $module['name']));
        $this->quizzes->save($lecture, ['pass_percent' => 70, 'questions' => $data]);
        $this->log[] = "Activity {$cmid}: quiz with ".count($data).' questions.';

    }

    /** @param array<string, mixed> $module */
    private function skip(array $module, ?Lecture $previous): ?Lecture
    {
        $this->log[] = "Activity {$module['cmid']}: {$module['type']} “{$module['name']}” has no equivalent here; left out.";

        return $previous;
    }

    private function lecture(Course $course, CourseSection $section, int $cmid, string $role, LectureKind $kind, string $title, ?string $articleHtml = null): Lecture
    {
        if ($id = LegacyLink::target('module', $cmid, $role)) {
            return Lecture::query()->findOrFail($id);
        }

        return DB::transaction(function () use ($course, $section, $cmid, $role, $kind, $title, $articleHtml) {
            $lecture = $section->lectures()->create([
                'course_id' => $course->id,
                'title' => Str::limit($title, 160, '') ?: 'Lecture',
                'kind' => $kind,
                'position' => (int) $section->lectures()->max('position') + 1,
                'article_html' => $articleHtml,
                // Reading time at ~200 words a minute, as in the builder.
                'duration_seconds' => $kind === LectureKind::Article ? max(60, (int) round(str_word_count(strip_tags((string) $articleHtml)) / 200 * 60)) : 0,
            ]);
            LegacyLink::remember('module', $cmid, $lecture, $role);

            return $lecture;
        });
    }

    /** @param array{area: string, name: string, hash: string, mime: string, size: int} $file */
    private function video(Bundle $bundle, Course $course, Lecture $lecture, array $file): void
    {
        if ($lecture->video_asset_id !== null) {
            // An earlier, interrupted run copied it: make sure it is (still) queued.
            $existing = VideoAsset::query()->find($lecture->video_asset_id);
            if ($existing && $existing->status === VideoStatus::Processing && $existing->processed_at === null) {
                ProcessVideo::dispatch($existing);
                $this->queued++;
                $this->log[] = "Video “{$file['name']}” queued again (not transcoded yet).";
            }

            return;
        }
        $path = $bundle->file($file['hash']);
        $asset = VideoAsset::query()->create([
            'owner_id' => $course->instructor_id,
            'provider' => 'local_hls',
            'original_name' => $file['name'],
            'source_size_bytes' => $file['size'],
        ]);
        $disk = Storage::disk('local');
        $sourcePath = "video-sources/{$asset->uuid}/source.".(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'mp4');
        $disk->makeDirectory(dirname($sourcePath));
        // Moved, not copied: the export and video storage share a disk, and the videos are big.
        if (! @rename($path, $disk->path($sourcePath)) && ! copy($path, $disk->path($sourcePath))) {
            throw new RuntimeException("Could not move {$file['name']} into video storage.");
        }

        DB::transaction(function () use ($asset, $lecture, $sourcePath) {
            $asset->forceFill(['status' => VideoStatus::Processing, 'source_path' => $sourcePath])->save();
            $lecture->forceFill(['video_asset_id' => $asset->id, 'duration_seconds' => 0])->save();
        });
        // Queued straight away, so a run that stops later still leaves this video processing.
        ProcessVideo::dispatch($asset);
        $this->queued++;
        $this->log[] = "Video “{$file['name']}” (".round($file['size'] / 1e9, 2).' GB) moved into video storage and queued for transcoding.';
    }

    /** @param array{area: string, name: string, hash: string, mime: string, size: int} $file */
    private function resource(Bundle $bundle, Lecture $lecture, array $file): void
    {
        if ($lecture->resources()->where('original_name', $file['name'])->exists()) {
            return;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
        $path = "lecture-resources/{$lecture->id}/".Str::random(32).".{$ext}";
        Storage::disk('local')->makeDirectory(dirname($path));
        copy($bundle->file($file['hash']), Storage::disk('local')->path($path));
        $lecture->resources()->create([
            'path' => $path,
            'original_name' => mb_substr($file['name'], 0, 200),
            'mime' => $file['mime'] === 'document/unknown' ? 'application/octet-stream' : $file['mime'],
            'size_bytes' => $file['size'],
        ]);
        $this->log[] = "“{$file['name']}” attached to “{$lecture->title}”.";
    }

    /** @param list<array{area: string, name: string, hash: string, mime: string, size: int}> $files */
    private function article(Bundle $bundle, string $html, array $files, Course $course): ?string
    {
        $converted = MoodleHtml::toArticle($html, function (string $name) use ($bundle, $files, $course): ?string {
            $file = collect($files)->first(fn (array $f) => $f['name'] === $name && str_starts_with($f['mime'], 'image/'));
            if ($file === null) {
                return null;
            }
            // Content-addressed, so the same picture used twice is stored once.
            $path = "lecture-images/{$course->id}/".substr($file['hash'], 0, 20).'.'.(strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'png');
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                $disk->makeDirectory(dirname($path));
                copy($bundle->file($file['hash']), $disk->path($path));
            }

            // Relative, so the page keeps working when the site moves to its final domain.
            return '/storage/'.$path;
        });

        return $this->sanitizer->cleanArticle($converted);
    }

    /** @param array{number: int, name: string} $section */
    private function sectionTitle(array $section): string
    {
        $name = $this->clean($section['name']);
        // "01 · GST Foundations…" — the number is shown by the curriculum itself.
        $name = trim(preg_replace('/^\d+\s*[·.:\-–—]\s*/u', '', $name) ?? $name);

        return Str::limit($name !== '' ? $name : ($section['number'] === 0 ? 'Start here' : "Section {$section['number']}"), 120, '');
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'course';
        $slug = $base;
        for ($i = 2; Course::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
