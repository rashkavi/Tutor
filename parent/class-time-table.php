<?php
// Class Time Table (parent): one class of the parent's child, same page as the student Class Time Table.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/parent-scope.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$classId = (int) ($_GET['class-id'] ?? 0);
if (!ps_class_children((int) $sessionUserObject['user_id'], $classId)) {
    header("Location: ./classes");
    exit;
}
$ttClass = Database::search("SELECT `class_id`, `class_name`, `class_cover` FROM `classes` WHERE `class_id` = '" . $classId . "'")->fetch_assoc();
$ttFrame = [
    'menu' => 'Classes',
    'side' => __DIR__ . '/includes/side-menu.php',
    'header' => dirname(__DIR__) . '/includes/header.php',
    'js' => ['../portal/js/time-table.js'],
];
$ttDetails = 'class-day?time-table-id=';
include dirname(__DIR__) . '/portal/includes/time-table-page.php';
