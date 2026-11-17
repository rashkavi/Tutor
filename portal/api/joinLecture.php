<?php
// Join Now of an online class day (student). The page never carries the raw Zoom link: this checks the student is in the
// class and the join window is open (1 hour before the start until the end, Asia/Colombo), marks the student as joined
// (attendance "Half" = joined online, the tutor confirms Present) when nothing is marked yet, then sends them to the link.
require_once '../../includes/UserClass.php';
require_once '../../includes/connection.php';
require_once '../../includes/enlixo/student-learning.php';

$back = static function (string $msg, int $id = 0): void {
    header('Location: ../time-table-class-details?' . ($id ? 'time-table-id=' . $id . '&' : '') . 'join=' . rawurlencode($msg));
    exit;
};
if (!SessionUser::ifUserIsStudent()) {
    header('Location: ../index');
    exit;
}
$studentId = (int) (SessionUser::getCurrentUserObject('Student')['student_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$res = Database::search("SELECT tt.`time_table_id`, tt.`date`, tt.`start_time`, tt.`end_time`, tt.`lecture`, tt.`lecture_type_lecture_type_id` lt,
        tt.`time_table_status_time_table_status_id` st, c.`delivery_mode`
    FROM `time_table` tt INNER JOIN `classes` c ON c.`class_id` = tt.`classes_class_id`
    INNER JOIN `students_has_classes` shc ON shc.`classes_class_id` = c.`class_id` AND shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1
    WHERE tt.`time_table_id` = '" . $id . "' LIMIT 1");
$l = $res ? $res->fetch_assoc() : null;
if (!$l) {
    header('Location: ../time-table');
    exit;
}
$tz = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);
$start = new DateTime($l['date'] . ' ' . $l['start_time'], $tz);
$end = new DateTime($l['date'] . ' ' . $l['end_time'], $tz);
$link = trim((string) $l['lecture']);
if ((int) $l['st'] !== 1) {
    $back('This class day is not on, so there is nothing to join.', $id);
}
if ($now < (clone $start)->modify('-1 hour')) {
    $back('You can join from ' . (clone $start)->modify('-1 hour')->format('g:i A') . ($start->format('Y-m-d') !== $now->format('Y-m-d') ? ' on ' . $start->format('j M') : '') . '.', $id);
}
if ($now > $end) {
    $back('This class has ended. Watch the recording when your tutor adds it.', $id);
}
if (!preg_match('~^https?://~i', $link)) {
    $back('Your tutor has not added the join link yet. Please check again shortly.', $id);
}
$marked = Database::search("SELECT 1 FROM `attendance` WHERE `time_table_time_table_id` = '" . $id . "' AND `students_student_id` = '" . $studentId . "' LIMIT 1");
if ($marked && $marked->num_rows === 0) {
    Database::iud("INSERT INTO `attendance` (`students_student_id`, `time_table_time_table_id`, `attendance_status_attendance_status_id`, `updated_at`)
        VALUES ('" . $studentId . "', '" . $id . "', 1, '" . $now->format('Y-m-d H:i:s') . "')");
}
header('Location: ' . $link);
exit;
