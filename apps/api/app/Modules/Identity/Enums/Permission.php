<?php

namespace App\Modules\Identity\Enums;

/**
 * Fine-grained staff permissions. The admin role holds all of them; support staff
 * can be given a subset (e.g. moderation without finance).
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
}
