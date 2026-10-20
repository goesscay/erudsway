<?php

namespace App\Modules\Fulfilment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Simulator\SimulatorClient;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** The extras a learner's edition includes, on their course page. */
class LearnerExtrasController extends Controller
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /** Simulator login (once ready) and the doubt session's state. */
    public function show(Request $request, string $slug): JsonResponse
    {
        [$user, $course] = $this->context($request, $slug);
        $tasks = FulfilmentTask::query()->where('user_id', $user->id)->where('course_id', $course->id)->get()->keyBy(fn (FulfilmentTask $t) => $t->type->value);
        $simulator = $tasks->get(TaskType::SimulatorAccess->value);
        $doubt = $tasks->get(TaskType::DoubtSession->value);

        return ApiResponse::data([
            /** @var array{status: string, username: string|null, password: string|null, gstin: string|null, login_url: string}|null */
            'simulator' => $simulator ? [
                'status' => $simulator->status === TaskStatus::Done ? 'ready' : 'preparing',
                'username' => $simulator->status === TaskStatus::Done ? ($simulator->data['username'] ?? null) : null,
                'password' => $simulator->status === TaskStatus::Done ? ($simulator->data['password'] ?? null) : null,
                'gstin' => $simulator->status === TaskStatus::Done ? ($simulator->data['gstin'] ?? null) : null,
                'login_url' => rtrim((string) config('services.simulator.url'), '/').'/gst/login',
            ] : null,
            /** @var array{status: string, preferred_times: string|null, questions: string|null, scheduled_for: string|null, notes: string|null}|null */
            'doubt_session' => $doubt ? [
                'status' => $doubt->status->value,
                'preferred_times' => $doubt->data['preferred_times'] ?? null,
                'questions' => $doubt->data['questions'] ?? null,
                'scheduled_for' => $doubt->scheduled_for?->toIso8601String(),
                'notes' => in_array($doubt->status, [TaskStatus::Scheduled, TaskStatus::Done], true) ? $doubt->staff_notes : null,
            ] : null,
        ]);
    }

    /** A one-time link that signs the learner into their simulator login. */
    public function simulatorLink(Request $request, string $slug, SimulatorClient $simulator): JsonResponse
    {
        [$user, $course] = $this->context($request, $slug);
        $task = FulfilmentTask::query()->where(['user_id' => $user->id, 'course_id' => $course->id, 'type' => TaskType::SimulatorAccess->value])->first();
        $username = $task?->status === TaskStatus::Done ? ($task->data['username'] ?? null) : null;
        if ($username === null) {
            throw new DomainException('SIMULATOR_NOT_READY', 'Your simulator login isn’t ready yet. It usually takes a few minutes.', 409);
        }

        try {
            return ApiResponse::data(['url' => $simulator->signInLink($username)]);
        } catch (Throwable $e) {
            report($e);
            throw new DomainException('SIMULATOR_UNAVAILABLE', 'The simulator couldn’t sign you in just now. Use your username and password on its login page instead.', 503);
        }
    }

    /** Ask for the doubt session: preferred times and what to cover. Staff then confirm a slot. */
    public function requestDoubtSession(Request $request, string $slug): JsonResponse
    {
        [$user, $course] = $this->context($request, $slug);
        $data = $request->validate([
            'preferred_times' => ['required', 'string', 'min:5', 'max:500'],
            'questions' => ['required', 'string', 'min:10', 'max:3000'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $task = FulfilmentTask::query()->where(['user_id' => $user->id, 'course_id' => $course->id, 'type' => TaskType::DoubtSession->value])->first();
        if ($task === null) {
            throw new DomainException('NOT_INCLUDED', 'Your edition doesn’t include a doubt session.', 403);
        }
        if (! in_array($task->status, [TaskStatus::AwaitingLearner, TaskStatus::Requested], true)) {
            throw new DomainException('ALREADY_SCHEDULED', 'Your doubt session has already been arranged.', 409);
        }

        $task->forceFill(['status' => TaskStatus::Requested, 'data' => $data])->save();

        return $this->show($request, $slug);
    }

    /** @return array{0: User, 1: Course} */
    private function context(Request $request, string $slug): array
    {
        /** @var User $user */
        $user = $request->user();
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        if ($this->entitlements->enrollment($user, $course) === null) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course first.', 403);
        }

        return [$user, $course];
    }
}
