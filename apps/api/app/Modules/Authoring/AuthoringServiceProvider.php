<?php

namespace App\Modules\Authoring;

use App\Models\User;
use App\Modules\Authoring\Policies\CoursePolicy;
use App\Modules\Catalog\Models\Course;
use App\Modules\Media\Models\UploadSession;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class AuthoringServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Course::class, CoursePolicy::class);
        Gate::define('author-courses', fn (User $user) => $user->isInstructor());

        // A 4 GB video is ~500 chunks of 8 MB; allow steady uploading without opening the floodgates.
        RateLimiter::for('upload-chunks', fn (Request $r) => Limit::perMinute(300)->by($r->user()?->getAuthIdentifier() ?? $r->ip()));

        Route::bind('session', fn (string $value) => UploadSession::query()->where('uuid', $value)->firstOrFail());
    }
}
