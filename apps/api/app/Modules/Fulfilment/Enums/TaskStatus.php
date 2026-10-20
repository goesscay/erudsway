<?php

namespace App\Modules\Fulfilment\Enums;

/**
 * Simulator access: pending → done (or failed, retried by staff).
 * Doubt session: awaiting_learner → requested → scheduled → done.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case AwaitingLearner = 'awaiting_learner';
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Done, self::Cancelled], true);
    }
}
