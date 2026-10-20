<?php

namespace App\Modules\Fulfilment\Enums;

enum TaskType: string
{
    case SimulatorAccess = 'simulator_access';
    case DoubtSession = 'doubt_session';

    public function label(): string
    {
        return match ($this) {
            self::SimulatorAccess => 'GST simulator access',
            self::DoubtSession => 'Faculty doubt session',
        };
    }
}
