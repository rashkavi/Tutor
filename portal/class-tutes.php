<?php
// Student → a class → Tutes: the tutes of one class (portal/tutes.php lists every class), cards like Class Recordings.
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
$tpClass = sl_student_class((int) $sessionUserObject['student_id'], (int) ($_GET['class-id'] ?? 0));
if (!$tpClass) {
    header("Location: ./tutes");
    exit;
}
include __DIR__ . '/includes/tutes-page.php';
