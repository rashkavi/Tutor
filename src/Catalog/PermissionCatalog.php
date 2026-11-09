<?php
declare(strict_types=1);

namespace Enlixo\Catalog;

/**
 * Fixed list of things a staff role can be allowed to do. Tutors build roles from these.
 * A permission with `feature` only works when the tutor's plan has that feature.
 */
final class PermissionCatalog
{
    /** @return array<string, array{name:string, module:string, feature?:string}> */
    public static function permissions(): array
    {
        return [
            'students.view'            => ['name' => 'View students', 'module' => 'Students'],
            'students.manage'          => ['name' => 'Add & edit students', 'module' => 'Students'],
            'students.approve_requests'=> ['name' => 'Approve join requests', 'module' => 'Students'],

            'classes.view'             => ['name' => 'View classes', 'module' => 'Classes & Timetable'],
            'classes.manage'           => ['name' => 'Create & edit classes', 'module' => 'Classes & Timetable'],
            'timetable.manage'         => ['name' => 'Manage timetable', 'module' => 'Classes & Timetable'],
            'zoom.schedule'            => ['name' => 'Schedule Zoom classes', 'module' => 'Classes & Timetable', 'feature' => 'zoom.tutor_integration'],

            'recordings.view'          => ['name' => 'View recordings', 'module' => 'Recordings'],
            'recordings.manage'        => ['name' => 'Upload & edit recordings', 'module' => 'Recordings'],
            'recordings.limits'        => ['name' => 'Set watch-time limits', 'module' => 'Recordings', 'feature' => 'recordings.controlled_access'],
            'recordings.grant_extra'   => ['name' => 'Give students extra watch time', 'module' => 'Recordings', 'feature' => 'recordings.controlled_access'],

            'tutes.manage'             => ['name' => 'Manage tutes & materials', 'module' => 'Tutes & Exams'],
            'exams.manage'             => ['name' => 'Create MCQ exams', 'module' => 'Tutes & Exams'],
            'exams.grade'              => ['name' => 'Grade exams', 'module' => 'Tutes & Exams'],

            'payments.view'            => ['name' => 'View payments', 'module' => 'Payments'],
            'payments.approve'         => ['name' => 'Approve / reject payments', 'module' => 'Payments'],
            'fees.manage'              => ['name' => 'Edit class fees & discounts', 'module' => 'Payments'],
            'payments.arrears'         => ['name' => 'View arrears dashboard', 'module' => 'Payments', 'feature' => 'payments.arrears'],

            'attendance.mark'          => ['name' => 'Mark attendance', 'module' => 'Attendance'],
            'attendance.export'        => ['name' => 'Export attendance', 'module' => 'Attendance', 'feature' => 'attendance.export'],

            'messages.send'            => ['name' => 'Send messages', 'module' => 'Messages'],

            'reports.view'             => ['name' => 'View reports', 'module' => 'Reports'],

            'settings.roles'           => ['name' => 'Manage roles', 'module' => 'Settings', 'feature' => 'staff.custom_roles'],
            'settings.staff'           => ['name' => 'Manage staff users', 'module' => 'Settings', 'feature' => 'staff.custom_roles'],
            'settings.integrations'    => ['name' => 'Manage integrations (Zoom, gateways)', 'module' => 'Settings'],
        ];
    }

    public static function exists(string $key): bool
    {
        return isset(self::permissions()[$key]);
    }
}
