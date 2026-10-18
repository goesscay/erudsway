<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\SubmitInstructorApplication;
use App\Modules\Identity\Http\Requests\StoreInstructorApplicationRequest;
use App\Modules\Identity\Http\Resources\InstructorApplicationResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorApplicationController extends Controller
{
    /** Apply to become an instructor. Requires a verified email. */
    public function store(StoreInstructorApplicationRequest $request, SubmitInstructorApplication $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $application = $submit->handle($user, $request->validated());

        return (new InstructorApplicationResource($application->refresh()))->response()->setStatusCode(201);
    }

    /** The signed-in user's most recent application, or null if they have not applied. */
    public function latest(Request $request): JsonResponse|InstructorApplicationResource
    {
        /** @var User $user */
        $user = $request->user();
        $application = $user->instructorApplications()->latest('id')->first();

        return $application ? new InstructorApplicationResource($application) : ApiResponse::data(null);
    }
}
