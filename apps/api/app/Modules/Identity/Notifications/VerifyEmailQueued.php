<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Laravel's verification email, sent from the queue so sign-up stays fast. */
class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
