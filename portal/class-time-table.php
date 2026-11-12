<?php
// One class's time table: the same calendar as time-table.php, showing only this class (V3 lecture-schedule-calender?subject-id=).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/student-learning.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$ttClass = sl_student_class((int) $sessionUserObject['student_id'], (int) ($_GET['class-id'] ?? 0));
if ($ttClass === null) {
    header("Location: ./classes");
    exit;
}
include __DIR__ . '/includes/time-table-page.php';
