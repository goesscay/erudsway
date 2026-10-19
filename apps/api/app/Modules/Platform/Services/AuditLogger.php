<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an action. The actor defaults to whoever is signed in on the *current* request
     * (read at call time, never cached), falling back to the session guard for console use.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): AuditLog {
        /** @var Request $request */
        $request = app('request');
        $actorId = $actor?->getKey() ?? $request->user()?->getAuthIdentifier() ?? Auth::guard('web')->id();

        return AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'before' => $before,
            'after' => $after,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
