<?php

namespace App\Modules\Authoring\Services;

use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Enums\VideoStatus;
use App\Support\Html\HtmlSanitizer;

/**
 * What a course needs before it can be submitted for review. Shown to instructors as a
 * checklist, and enforced on submit.
 */
class CourseReadiness
{
    public const MIN_LECTURES = 5;

    public const MIN_VIDEO_SECONDS = 30 * 60;

    public const MIN_OUTCOMES = 4;

    /**
     * @return list<array{key: string, group: string, label: string, ok: bool}>
     */
    public function check(Course $course): array
    {
        $course->loadMissing(['lectures.videoAsset', 'category']);
        $lectures = $course->lectures;
        $videos = $lectures->where('kind', LectureKind::Video);
        $videoSeconds = (int) $videos->sum(fn (Lecture $l) => $l->videoAsset?->status === VideoStatus::Ready ? $l->duration_seconds : 0);

        $items = [
            ['landing', 'title', 'A clear title (at least 10 characters)', mb_strlen($course->title) >= 10],
            ['landing', 'subtitle', 'A subtitle (at least 20 characters)', mb_strlen((string) $course->subtitle) >= 20],
            ['landing', 'description', 'A description of at least 200 characters', HtmlSanitizer::textLength($course->description) >= 200],
            ['landing', 'category', 'A subcategory', $course->category->parent_id !== null],
            ['landing', 'thumbnail', 'A course image', $course->thumbnail_path !== null],
            ['learners', 'outcomes', 'At least '.self::MIN_OUTCOMES.' learning outcomes', count($course->outcomes) >= self::MIN_OUTCOMES],
            ['learners', 'requirements', 'Requirements or prerequisites', count($course->requirements) >= 1],
            ['learners', 'audience', 'Who the course is for', count($course->audience) >= 1],
            ['curriculum', 'lectures', 'At least '.self::MIN_LECTURES.' lectures', $lectures->count() >= self::MIN_LECTURES],
            ['curriculum', 'video_minutes', 'At least 30 minutes of processed video', $videoSeconds >= self::MIN_VIDEO_SECONDS],
            ['curriculum', 'videos_ready', 'Every video lecture has a processed video', $videos->every(fn (Lecture $l) => $l->videoAsset?->status === VideoStatus::Ready)],
            ['curriculum', 'articles', 'Every article lecture has content', $lectures->where('kind', LectureKind::Article)->every(fn (Lecture $l) => HtmlSanitizer::textLength($l->article_html) >= 50)],
            ['curriculum', 'quizzes', 'Every quiz has at least one question', $this->quizzesReady($lectures->where('kind', LectureKind::Quiz)->pluck('id')->all())],
            ['curriculum', 'assignments', 'Every assignment has instructions', $this->assignmentsReady($lectures->where('kind', LectureKind::Assignment)->pluck('id')->all())],
            ['pricing', 'pricing', 'A price (or free) chosen', $course->pricing_confirmed_at !== null],
        ];

        return array_map(fn (array $i) => ['group' => $i[0], 'key' => $i[1], 'label' => $i[2], 'ok' => $i[3]], $items);
    }

    /** @param list<int> $lectureIds */
    private function quizzesReady(array $lectureIds): bool
    {
        if ($lectureIds === []) {
            return true;
        }
        $withQuestions = Quiz::query()->whereIn('lecture_id', $lectureIds)->whereHas('questions')->count();

        return $withQuestions === count($lectureIds);
    }

    /** @param list<int> $lectureIds */
    private function assignmentsReady(array $lectureIds): bool
    {
        if ($lectureIds === []) {
            return true;
        }

        return Assignment::query()->whereIn('lecture_id', $lectureIds)->get()
            ->filter(fn (Assignment $a) => HtmlSanitizer::textLength($a->instructions_html) >= 20)
            ->count() === count($lectureIds);
    }

    public function isReady(Course $course): bool
    {
        return collect($this->check($course))->every(fn (array $item) => $item['ok']);
    }
}
