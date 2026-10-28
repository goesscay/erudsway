<?php

namespace App\Modules\Platform\Enums;

/** What a Contact page message is about. */
enum ContactTopic: string
{
    case Course = 'course';
    case Payment = 'payment';
    case Technical = 'technical';
    case Simulator = 'simulator';
    case Teaching = 'teaching';
    case Partnership = 'partnership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Course enquiry',
            self::Payment => 'Payment, invoice or order',
            self::Technical => 'Login or technical help',
            self::Simulator => 'GST simulator',
            self::Teaching => 'Teaching on Erudsway',
            self::Partnership => 'College or corporate partnership',
            self::Other => 'Something else',
        };
    }
}
