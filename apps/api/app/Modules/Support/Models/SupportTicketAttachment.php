<?php

namespace App\Modules\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A screenshot or PDF on a ticket, kept on the private disk and downloaded only by the ticket's
 * owner or support staff.
 *
 * @property int $id
 * @property string $uuid
 * @property int $ticket_id
 * @property int|null $message_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property-read SupportTicket $ticket
 * @property-read SupportTicketMessage|null $message
 */
class SupportTicketAttachment extends Model
{
    use HasUuids;

    protected $fillable = ['ticket_id', 'message_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /** @return BelongsTo<SupportTicketMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'message_id');
    }
}
