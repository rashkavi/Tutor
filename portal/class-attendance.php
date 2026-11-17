<?php
// Student → a class → Attendance: the Attendance calendar of one class (portal/attendance.php shows every class).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/student-learning.php';
require_once __DIR__ . '/../includes/enlixo/student-attendance.php';
$ttClass = sl_student_class((int) $sessionUserObject['student_id'], (int) ($_GET['class-id'] ?? 0));
if (!$ttClass) {
    header("Location: ./attendance");
    exit;
}
$ttAttendance = true;
$ttTiles = sa_attendance_tiles((int) $sessionUserObject['student_id'], (int) $ttClass['class_id']);
include __DIR__ . '/includes/time-table-page.php';
