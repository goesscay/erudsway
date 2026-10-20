<?php

namespace App\Modules\Authoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Services\Assignments;
use App\Modules\Assessment\Services\Quizzes;
use App\Modules\Authoring\Http\Requests\ReorderCurriculumRequest;
use App\Modules\Authoring\Http\Resources\CourseEditorResource;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Media\Jobs\DeleteVideoAsset;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Sections and lectures. Every endpoint returns the updated builder payload so the
 * client always renders the server's view of the curriculum.
 */
class CurriculumController extends Controller
{
    public function __construct(private readonly CourseStats $stats) {}

    public function storeSection(Request $request, Course $course): CourseEditorResource
    {
        Gate::authorize('update', $course);
        $data = $request->validate(['title' => ['required', 'string', 'min:3', 'max:120']]);

        $course->sections()->create([
            'title' => $data['title'],
            'position' => (int) $course->sections()->max('position') + 1,
        ]);

        return CourseEditorResource::load($course);
    }

    public function updateSection(Request $request, CourseSection $section): CourseEditorResource
    {
        Gate::authorize('update', $section->course);
        $data = $request->validate(['title' => ['required', 'string', 'min:3', 'max:120']]);
        $section->update($data);

        return CourseEditorResource::load($section->course);
    }

    public function destroySection(CourseSection $section): CourseEditorResource
    {
        $course = $section->course;
        Gate::authorize('update', $course);
        if ($course->sections()->count() <= 1) {
            throw new DomainException('LAST_SECTION', 'A course needs at least one section.', 422);
        }

        $assetIds = $section->lectures()->whereNotNull('video_asset_id')->pluck('video_asset_id');
        $this->deleteResourceFiles($section->lectures()->pluck('id')->all());
        $section->delete();
        $this->renumberSections($course);
        $this->stats->refresh($course);
        $assetIds->each(fn (int $id) => DeleteVideoAsset::dispatch($id));

        return CourseEditorResource::load($course);
    }

    public function storeLecture(Request $request, CourseSection $section): CourseEditorResource
    {
        Gate::authorize('update', $section->course);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'kind' => ['required', Rule::enum(LectureKind::class)],
        ]);

        $section->lectures()->create([
            'course_id' => $section->course_id,
            'title' => $data['title'],
            'kind' => $data['kind'],
            'position' => (int) $section->lectures()->max('position') + 1,
            // Listed length until the quiz or brief is written.
            'duration_seconds' => match ($data['kind']) {
                LectureKind::Quiz->value => Quizzes::SECONDS_PER_QUESTION,
                LectureKind::Assignment->value => Assignments::DURATION_SECONDS,
                default => 0,
            },
        ]);
        $this->stats->refresh($section->course_id);

        return CourseEditorResource::load($section->course);
    }

    public function updateLecture(Request $request, Lecture $lecture, HtmlSanitizer $sanitizer): CourseEditorResource
    {
        Gate::authorize('update', $lecture->course);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:160'],
            'is_free_preview' => ['sometimes', 'boolean'],
            'article_html' => ['sometimes', 'nullable', 'string', 'max:50000'],
            // Hold the lecture back to an edition (null: every edition).
            'min_edition_id' => ['sometimes', 'nullable', 'integer', Rule::exists('course_editions', 'id')->where('course_id', $lecture->course_id)],
        ]);

        if (array_key_exists('article_html', $data)) {
            if ($lecture->kind !== LectureKind::Article) {
                throw new DomainException('NOT_AN_ARTICLE', 'Only article lectures have text content.', 422);
            }
            $data['article_html'] = $sanitizer->cleanArticle($data['article_html']);
            // Reading time at ~200 words a minute, at least one minute.
            $words = str_word_count(strip_tags((string) $data['article_html']));
            $data['duration_seconds'] = max(60, (int) round($words / 200 * 60));
        }

        if (array_key_exists('min_edition_id', $data)) {
            $lecture->min_edition_id = $data['min_edition_id'];
            unset($data['min_edition_id']);
        }
        $lecture->fill($data);
        if (array_key_exists('duration_seconds', $data)) {
            $lecture->duration_seconds = $data['duration_seconds'];
        }
        $lecture->save();
        $this->stats->refresh($lecture->course_id);

        return CourseEditorResource::load($lecture->course);
    }

    public function destroyLecture(Lecture $lecture): CourseEditorResource
    {
        $course = $lecture->course;
        Gate::authorize('update', $course);

        $assetId = $lecture->video_asset_id;
        $sectionId = $lecture->section_id;
        $this->deleteResourceFiles([$lecture->id]);
        $lecture->delete();
        $this->renumberLectures($sectionId);
        $this->stats->refresh($course);
        if ($assetId) {
            DeleteVideoAsset::dispatch($assetId);
        }

        return CourseEditorResource::load($course);
    }

    /** Apply a complete new order of sections and lectures (lectures may change section). */
    public function reorder(ReorderCurriculumRequest $request, Course $course): CourseEditorResource
    {
        Gate::authorize('update', $course);

        /** @var list<array{id: int, lectures: list<int>}> $sections */
        $sections = $request->validated('sections');
        $sectionIds = array_map(fn ($s) => (int) $s['id'], $sections);
        $lectureIds = array_merge(...array_map(fn ($s) => array_map('intval', $s['lectures']), $sections ?: [['lectures' => []]]));

        $currentSections = $course->sections()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $currentLectures = $course->lectures()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sortedSections = $sectionIds;
        sort($sortedSections);
        $sortedLectures = $lectureIds;
        sort($sortedLectures);

        if ($sortedSections !== $currentSections || $sortedLectures !== $currentLectures) {
            throw new DomainException('CURRICULUM_MISMATCH', 'The curriculum changed in another window. Reload and try again.', 409);
        }

        DB::transaction(function () use ($sections) {
            foreach ($sections as $i => $section) {
                CourseSection::query()->whereKey($section['id'])->update(['position' => $i + 1]);
                foreach ($section['lectures'] as $j => $lectureId) {
                    Lecture::query()->whereKey($lectureId)->update(['section_id' => $section['id'], 'position' => $j + 1]);
                }
            }
        });

        return CourseEditorResource::load($course);
    }

    private function renumberSections(Course $course): void
    {
        $course->sections()->get()->each(fn (CourseSection $s, int $i) => $s->update(['position' => $i + 1]));
    }

    private function renumberLectures(int $sectionId): void
    {
        Lecture::query()->where('section_id', $sectionId)->orderBy('position')->get()
            ->each(fn (Lecture $l, int $i) => $l->update(['position' => $i + 1]));
    }

    /**
     * Downloadable resources and learners' assignment files of the given lectures.
     *
     * @param  list<int>  $lectureIds
     */
    private function deleteResourceFiles(array $lectureIds): void
    {
        $paths = DB::table('lecture_resources')->whereIn('lecture_id', $lectureIds)->pluck('path')->all();
        if ($paths) {
            Storage::disk('local')->delete($paths);
        }
        foreach ($lectureIds as $id) {
            Storage::disk('local')->deleteDirectory("assignments/{$id}");
        }
    }
}
