<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user as the frontend sees them.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $instructor = $this->instructorProfile;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'status' => $this->status->value,
            /** @var list<string> */
            'roles' => $this->getRoleNames()->values()->all(),
            /** @var list<string> */
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'profile' => [
                'headline' => $profile?->headline,
                'bio' => $profile?->bio,
                'phone' => $profile?->phone,
                'timezone' => $profile->timezone ?? 'Asia/Kolkata',
            ],
            'instructor' => $instructor ? [
                'slug' => $instructor->slug,
                'display_name' => $instructor->display_name,
                'approved_at' => $instructor->approved_at->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
