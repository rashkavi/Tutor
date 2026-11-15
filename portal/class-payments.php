<?php
// Class Payments (student): the fees of one class, same layout as Payments (includes/enlixo/student-fees.php).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/student-fees.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$studentId = (int) $sessionUserObject['student_id'];
$class = sl_student_class($studentId, (int) ($_GET['class-id'] ?? 0));
if (!$class) {
    header("Location: ./classes");
    exit;
}
$classId = (int) $class['class_id'];
$rows = sf_fees($studentId, $classId);

$lf = ['title' => 'Class Payments', 'menu' => 'Classes', 'css' => [Project::getAssetsPath() . '/css/student-fees.css']];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title($class['class_name'], [['Classes', './classes'], ['Class Details', './class-details?class-id=' . $classId], ['Class Payments', '']], '', '<a class="custom-outline-btn" href="./payments">' . enlixo_icon('payments', 18) . 'All Payments</a>');
sf_render($rows, 'Fees of this class appear here once your tutor sets up its payment plan.');
include __DIR__ . '/includes/learning-frame-bottom.php';
