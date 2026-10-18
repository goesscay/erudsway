<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    /** The signed-in user, with roles, permissions and profile. */
    public function show(Request $request): UserResource
    {
        return new UserResource($this->user($request)->load(['profile', 'instructorProfile']));
    }

    /** Update name and profile details. */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $this->user($request);
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            if (array_key_exists('name', $data)) {
                $user->update(['name' => $data['name']]);
            }
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                array_intersect_key($data, array_flip(['headline', 'bio', 'phone', 'timezone'])),
            );
        });

        return new UserResource($user->fresh(['profile', 'instructorProfile']));
    }

    /** Change password; requires the current password. */
    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $audit): Response
    {
        $user = $this->user($request);
        $user->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();
        $audit->record('user.password_changed', $user);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
