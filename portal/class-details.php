<?php
// Student → a class (portal/class-details.php?class-id=): the class at a glance and every part of it
// (includes/enlixo/class-overview.php, the same page the parent sees). Same frame as Time Table and Recordings.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/class-overview.php';
$classId = (int) ($_GET['class-id'] ?? 0);
$o = co_overview((int) $sessionUserObject['student_id'], $classId);
if (!$o) {
    header("Location: ./classes");
    exit;
}
$lf = ['title' => $o['name'], 'menu' => 'Classes'];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title($o['name'], [['Classes', './classes'], ['Class Details', null]], '',
    '<a class="custom-outline-btn" href="./class-time-table?class-id=' . $classId . '">' . enlixo_icon('timetable', 18) . 'Time Table</a>'
    . '<a class="custom-btn" href="./class-payments?class-id=' . $classId . '">' . enlixo_icon('payments', 18) . 'Payments</a>');
co_render($o, [
    'timetable' => './class-time-table?class-id=' . $classId, 'attendance' => './class-attendance?class-id=' . $classId,
    'recordings' => './recordings?class=' . $classId, 'pre' => './pre-recorded-classes?class=' . $classId,
    'tutes' => './class-tutes?class-id=' . $classId, 'payments' => './class-payments?class-id=' . $classId,
    'day' => './time-table-class-details?time-table-id=', 'recording' => './watch-recording?recording-id=',
    'tute' => './downloadable-tute?class-id=' . $classId . '&tute-id=',
]);
include __DIR__ . '/includes/learning-frame-bottom.php';
