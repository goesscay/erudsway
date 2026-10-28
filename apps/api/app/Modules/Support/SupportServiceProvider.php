<?php

namespace App\Modules\Support;

use App\Support\Modules\ModuleServiceProvider;

/**
 * The Help Centre's support desk: learners raise tickets (with screenshots), follow the
 * conversation and close them; staff with `handle_support` work the queue, reply, keep
 * internal notes and move tickets through Open → In progress → Waiting for learner → Resolved → Closed.
 */
class SupportServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
