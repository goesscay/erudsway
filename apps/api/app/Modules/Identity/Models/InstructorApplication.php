<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Enums\TeachingExperience;
use Database\Factories\InstructorApplicationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property ApplicationStatus $status
 * @property string $headline
 * @property list<string> $topics
 * @property string $experience
 * @property TeachingExperience $teaching_experience
 * @property string|null $sample_url
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read User|null $reviewer
 */
class InstructorApplication extends Model
{
    /** @use HasFactory<InstructorApplicationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['headline', 'topics', 'experience', 'teaching_experience', 'sample_url'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'teaching_experience' => TeachingExperience::class,
            'topics' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InstructorApplicationFactory
    {
        return InstructorApplicationFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }
}
