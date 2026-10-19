<?php

namespace App\Modules\Media\Jobs;

use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Video\VideoProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Runs on the dedicated "media" queue (one worker, low priority) so the site stays fast. */
class ProcessVideo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public int $timeout;

    public function __construct(public readonly VideoAsset $asset)
    {
        $this->onQueue('media');
        $this->timeout = (int) config('media.transcode_timeout') + 120;
    }

    public function uniqueId(): string
    {
        return $this->asset->uuid;
    }

    public function handle(VideoProvider $provider, CourseStats $stats): void
    {
        $asset = $this->asset->fresh();
        if (! $asset || $asset->status !== VideoStatus::Processing) {
            return;
        }

        $provider->process($asset);

        // Lecture lengths and course totals follow the processed video.
        Lecture::query()->where('video_asset_id', $asset->id)->get()->each(function (Lecture $lecture) use ($asset, $stats) {
            $lecture->forceFill(['duration_seconds' => $asset->duration_seconds])->save();
            $stats->refresh($lecture->course_id);
        });
    }

    public function failed(Throwable $e): void
    {
        $this->asset->forceFill([
            'status' => VideoStatus::Failed,
            'failure_reason' => mb_substr($e->getMessage(), 0, 500),
        ])->save();
    }
}
