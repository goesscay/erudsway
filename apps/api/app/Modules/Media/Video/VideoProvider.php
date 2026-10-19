<?php

namespace App\Modules\Media\Video;

use App\Modules\Media\Models\VideoAsset;

/**
 * Where course videos are processed and stored. Courses, lectures and the player only
 * talk to this interface, so moving to Cloudflare Stream later is a new implementation,
 * not a rewrite (decision D1).
 */
interface VideoProvider
{
    public function name(): string;

    /** Turn the uploaded source into a streamable asset. Sets status ready (or throws). */
    public function process(VideoAsset $asset): void;

    /** Remove everything stored for the asset. */
    public function delete(VideoAsset $asset): void;
}
