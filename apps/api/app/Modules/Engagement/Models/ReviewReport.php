<?php

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $review_id
 * @property int $user_id
 * @property string $reason
 */
class ReviewReport extends Model
{
    protected $fillable = ['review_id', 'user_id', 'reason'];
}
