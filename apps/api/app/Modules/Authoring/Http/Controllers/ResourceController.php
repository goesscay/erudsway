<?php

namespace App\Modules\Authoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authoring\Http\Resources\CourseEditorResource;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\LectureResource;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloadable files attached to lectures (PDFs, spreadsheets, templates). */
class ResourceController extends Controller
{
    private const MAX_PER_LECTURE = 10;

    public function store(Request $request, Lecture $lecture): CourseEditorResource
    {
        Gate::authorize('update', $lecture->course);
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', config('media.resources.mimes')), 'max:'.config('media.resources.max_kb')],
        ], ['file.mimes' => 'Upload a PDF, Excel, Word, PowerPoint, CSV, ZIP or text file.']);

        if ($lecture->resources()->count() >= self::MAX_PER_LECTURE) {
            throw new DomainException('TOO_MANY_RESOURCES', 'A lecture can have at most '.self::MAX_PER_LECTURE.' files.', 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        // Random stored name; the original name is only ever used as a download filename.
        $path = $file->storeAs("lecture-resources/{$lecture->id}", Str::random(32).'.'.$ext, 'local');

        $lecture->resources()->create([
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
        ]);

        return CourseEditorResource::load($lecture->course);
    }

    public function destroy(LectureResource $resource): CourseEditorResource
    {
        $course = $resource->lecture->course;
        Gate::authorize('update', $course);

        Storage::disk('local')->delete($resource->path);
        $resource->delete();

        return CourseEditorResource::load($course);
    }

    /** Lets the instructor (or a moderator) check an uploaded file. Learner downloads arrive in Phase 4. */
    public function download(LectureResource $resource): StreamedResponse
    {
        Gate::authorize('view', $resource->lecture->course);

        return Storage::disk('local')->download($resource->path, $resource->original_name);
    }
}
