<?php

namespace App\Modules\Fulfilment\Enums;

enum TaskType: string
{
    case SimulatorAccess = 'simulator_access';
    /** A free 7-day simulator login the learner starts themselves (one per account). */
    case SimulatorTrial = 'simulator_trial';
    case DoubtSession = 'doubt_session';

    public function label(): string
    {
        return match ($this) {
            self::SimulatorAccess => 'GST simulator access',
            self::SimulatorTrial => 'GST simulator trial',
            self::DoubtSession => 'Faculty doubt session',
        };
    }
}
