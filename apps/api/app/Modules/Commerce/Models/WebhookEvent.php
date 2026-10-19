<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every gateway webhook we receive, stored before processing. The unique event id makes
 * redelivered events no-ops.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
