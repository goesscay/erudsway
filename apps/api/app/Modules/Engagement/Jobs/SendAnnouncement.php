<?php

namespace App\Modules\Engagement\Jobs;

use App\Models\User;
use App\Modules\Engagement\Models\Announcement;
use App\Modules\Engagement\Notifications\CourseAnnouncement;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/** Notify every active learner of the course, in batches. Runs once per announcement. */
class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $announcementId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $announcement = Announcement::query()->findOrFail($this->announcementId);
        if ($announcement->sent_at !== null) {
            return;
        }

        $count = 0;
        Enrollment::query()->active()->where('course_id', $announcement->course_id)
            ->select('user_id')->orderBy('id')
            ->chunk(200, function ($rows) use ($announcement, &$count) {
                $users = User::query()->whereIn('id', $rows->pluck('user_id'))->where('status', 'active')->get();
                Notification::send($users, new CourseAnnouncement($announcement));
                $count += $users->count();
            });

        $announcement->forceFill(['sent_at' => now(), 'recipients_count' => $count])->save();
    }
}
