<?php

namespace App\Modules\Identity\Enums;

/**
 * Fine-grained staff permissions. The admin role holds all of them; other staff get a
 * subset, directly or through a custom role (e.g. moderation without finance).
 */
enum Permission: string
{
    case ReviewInstructorApplications = 'review_instructor_applications';
    case ModerateCourses = 'moderate_courses';
    case ManageCourses = 'manage_courses';
    case ManageCategories = 'manage_categories';
    case ManageFulfilment = 'manage_fulfilment';
    case ManageUsers = 'manage_users';
    case ManageFinance = 'manage_finance';
    case ViewReports = 'view_reports';
    case ManageSettings = 'manage_settings';
    case ViewAuditLogs = 'view_audit_logs';

    public function label(): string
    {
        return match ($this) {
            self::ReviewInstructorApplications => 'Instructor applications',
            self::ModerateCourses => 'Course and review moderation',
            self::ManageCourses => 'Course management',
            self::ManageCategories => 'Categories',
            self::ManageFulfilment => 'Fulfilment',
            self::ManageUsers => 'Users',
            self::ManageFinance => 'Finance',
            self::ViewReports => 'Reports',
            self::ManageSettings => 'Settings',
            self::ViewAuditLogs => 'Audit log',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReviewInstructorApplications => 'Approve or reject people applying to teach.',
            self::ModerateCourses => 'Review courses before they go live; hide reported reviews and Q&A.',
            self::ManageCourses => 'Edit any course in the builder, publish, archive or feature it, and change its instructor.',
            self::ManageCategories => 'Add, rename, reorder, hide and delete catalogue categories.',
            self::ManageFulfilment => 'Simulator logins and doubt sessions that editions include.',
            self::ManageUsers => 'Find and add users, suspend them, grant or revoke course access, and see roles.',
            self::ManageFinance => 'Orders, refunds, coupons, payouts and the ledger.',
            self::ViewReports => 'The dashboard numbers and every report, with CSV exports.',
            self::ManageSettings => 'GST, invoice, refund and payout settings.',
            self::ViewAuditLogs => 'See and export the record of staff actions.',
        };
    }
}
