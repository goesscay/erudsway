<?php

namespace App\Modules\Support\Enums;

/** Help Centre topics; a ticket is filed under one. */
enum TicketCategory: string
{
    case Account = 'account';
    case Courses = 'courses';
    case GstSimulator = 'gst_simulator';
    case TdsSimulator = 'tds_simulator';
    case Payments = 'payments';
    case Certificates = 'certificates';
    case Faculty = 'faculty';
    case Technical = 'technical';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Account & Login',
            self::Courses => 'Courses & Learning',
            self::GstSimulator => 'GST Simulator',
            self::TdsSimulator => 'TDS Simulator',
            self::Payments => 'Payments & Refunds',
            self::Certificates => 'Certificates',
            self::Faculty => 'Faculty & Instructors',
            self::Technical => 'Technical Support',
        };
    }
}
