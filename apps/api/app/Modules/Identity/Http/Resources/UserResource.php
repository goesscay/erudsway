<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use App\Modules\Identity\Services\Impersonation;
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
            // Set when an admin is viewing the site as this user (read-only).
            'impersonator' => $this->impersonator($request),
        ];
    }

    /** @return array{name: string}|null */
    private function impersonator(Request $request): ?array
    {
        $id = app(Impersonation::class)->impersonatorId($request);
        if ($id === null || ! $request->user()?->is($this->resource)) {
            return null;
        }

        return ['name' => (string) User::query()->whereKey($id)->value('name')];
    }
}
