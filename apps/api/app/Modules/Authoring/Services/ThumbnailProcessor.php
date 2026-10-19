<?php

namespace App\Modules\Authoring\Services;

use App\Modules\Catalog\Models\Course;
use App\Support\Http\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-encodes course images to a fixed 16:9 JPEG. Re-encoding strips metadata and anything
 * hidden in the original file.
 */
class ThumbnailProcessor
{
    public function store(Course $course, UploadedFile $file): string
    {
        $data = file_get_contents($file->getRealPath());
        $image = $data !== false ? @imagecreatefromstring($data) : false;
        if ($image === false) {
            throw new DomainException('INVALID_IMAGE', 'That file could not be read as an image.', 422);
        }

        $w = (int) config('media.thumbnails.width');
        $h = (int) config('media.thumbnails.height');
        $sw = imagesx($image);
        $sh = imagesy($image);
        if ($sw < 750 || $sh < 422) {
            imagedestroy($image);
            throw new DomainException('IMAGE_TOO_SMALL', 'Use an image at least 750 × 422 pixels.', 422);
        }

        // Centre-crop to 16:9, then scale.
        $targetRatio = $w / $h;
        if ($sw / $sh > $targetRatio) {
            $cropH = $sh;
            $cropW = (int) round($sh * $targetRatio);
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $targetRatio);
        }
        $canvas = imagecreatetruecolor($w, $h);
        imagecopyresampled($canvas, $image, 0, 0, (int) (($sw - $cropW) / 2), (int) (($sh - $cropH) / 2), $w, $h, $cropW, $cropH);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        $path = "course-thumbnails/{$course->uuid}-".Str::lower(Str::random(8)).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        $old = $course->thumbnail_path;
        $course->forceFill(['thumbnail_path' => $path])->save();
        if ($old && ! Str::startsWith($old, ['http://', 'https://'])) {
            Storage::disk('public')->delete($old);
        }

        return $path;
    }
}
