<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Laravel's password reset email, sent from the queue. */
class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
