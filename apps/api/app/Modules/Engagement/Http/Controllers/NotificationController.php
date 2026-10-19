<?php

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Engagement\Models\NotificationPreference;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/** The in-app bell and email preferences. */
class NotificationController extends Controller
{
    /** The latest 20 notifications and how many are unread. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return ApiResponse::data([
            'unread' => $user->unreadNotifications()->count(),
            /** @var list<array{id: string, kind: string, title: string, body: string, url: string, read: bool, created_at: string}> */
            'items' => $user->notifications()->latest()->limit(20)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'kind' => (string) ($n->data['kind'] ?? ''),
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => (string) ($n->data['body'] ?? ''),
                'url' => (string) ($n->data['url'] ?? '/'),
                'read' => $n->read(),
                'created_at' => $n->created_at?->toIso8601String() ?? '',
            ])->values()->all(),
        ]);
    }

    public function read(Request $request, string $id): Response
    {
        $this->user($request)->notifications()->whereKey($id)->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function preferences(Request $request): JsonResponse
    {
        return ApiResponse::data($this->prefs(NotificationPreference::for($this->user($request)->id)));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate(array_fill_keys(NotificationPreference::TOPICS, ['sometimes', 'boolean']));
        $prefs = NotificationPreference::for($this->user($request)->id);
        $prefs->fill($data)->save();

        return ApiResponse::data($this->prefs($prefs));
    }

    /** @return array{course_announcements: bool, qa_replies: bool, assignment_feedback: bool, instructor_questions: bool, instructor_reviews: bool, instructor_submissions: bool, promotions: bool} */
    private function prefs(NotificationPreference $p): array
    {
        return [
            'course_announcements' => $p->course_announcements,
            'qa_replies' => $p->qa_replies,
            'assignment_feedback' => $p->assignment_feedback,
            'instructor_questions' => $p->instructor_questions,
            'instructor_reviews' => $p->instructor_reviews,
            'instructor_submissions' => $p->instructor_submissions,
            'promotions' => $p->promotions,
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
