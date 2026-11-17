<?php
// Student → Attendance: every class day the student was marked for, on the same calendar as the Time Table
// (portal/includes/time-table-page.php in attendance mode), with the standard stat tiles on top.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/student-attendance.php';
$ttClass = null;
$ttAttendance = true;
$ttTiles = sa_attendance_tiles((int) $sessionUserObject['student_id']);
include __DIR__ . '/includes/time-table-page.php';
