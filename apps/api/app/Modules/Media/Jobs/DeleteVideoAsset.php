<?php

namespace App\Modules\Media\Jobs;

use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Video\VideoProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Removes a replaced or orphaned video's files and record. */
class DeleteVideoAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $assetId)
    {
        $this->onQueue('media');
    }

    public function handle(VideoProvider $provider): void
    {
        $asset = VideoAsset::query()->find($this->assetId);
        if (! $asset || Lecture::query()->where('video_asset_id', $asset->id)->exists()) {
            return; // gone already, or still in use
        }

        $provider->delete($asset);
        $asset->delete();
    }
}
