<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $bio
 * @property string|null $avatar_path
 * @property string $locale
 * @property string $timezone
 * @property string $country
 * @property string|null $phone
 */
class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['headline', 'bio', 'locale', 'timezone', 'country', 'phone'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'locale' => 'en-IN',
        'timezone' => 'Asia/Kolkata',
        'country' => 'IN',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
