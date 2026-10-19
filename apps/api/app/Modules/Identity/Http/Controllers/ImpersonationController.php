<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Services\Impersonation;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /** End "view as" and return to the admin's own account. */
    public function stop(Request $request, Impersonation $impersonation): UserResource
    {
        return new UserResource($impersonation->stop($request)->load(['profile', 'instructorProfile']));
    }
}
