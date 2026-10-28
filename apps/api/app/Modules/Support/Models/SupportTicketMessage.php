<?php

namespace App\Modules\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property bool $from_staff
 * @property bool $internal Staff-only note, never shown to the learner
 * @property string $body
 * @property Carbon $created_at
 * @property-read User|null $author
 * @property-read SupportTicket $ticket
 */
class SupportTicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_id', 'from_staff', 'internal', 'body'];

    protected function casts(): array
    {
        return ['from_staff' => 'boolean', 'internal' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /** @return HasMany<SupportTicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'message_id');
    }
}
