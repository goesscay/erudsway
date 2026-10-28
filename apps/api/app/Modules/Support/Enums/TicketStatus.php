<?php

namespace App\Modules\Support\Enums;

/** Open → In progress → Waiting for the learner → Resolved → Closed. A reply reopens a resolved ticket. */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingLearner = 'waiting_learner';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::WaitingLearner => 'Waiting for learner',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /** The same status in the learner's own words. */
    public function learnerLabel(): string
    {
        return $this === self::WaitingLearner ? 'Waiting for your reply' : $this->label();
    }

    /** Still needs the support team's attention. */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }
}
