<?php
// Class Payments (parent): the fees of one class for the parent's child in it (chips when two children share it).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/parent-scope.php";
require_once __DIR__ . "/includes/child-switch.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$parentId = (int) $sessionUserObject['user_id'];
$classId = (int) ($_GET['class-id'] ?? 0);
$inClass = ps_class_children($parentId, $classId);
if (!$inClass) {
    header("Location: ./classes");
    exit;
}
$children = array_intersect_key(ps_children($parentId), array_flip($inClass));
$childId = (int) ($_GET['child'] ?? 0);
if (!isset($children[$childId])) {
    $childId = (int) array_key_first($children);
}
$className = (string) (Database::search("SELECT class_name FROM classes WHERE class_id = '" . $classId . "'")->fetch_assoc()['class_name'] ?? 'Class');
$rows = sf_fees($childId, $classId);

$lf = [
    'title' => 'Class Payments', 'menu' => 'Classes',
    'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php',
    'css' => [Project::getAssetsPath() . '/css/student-fees.css', Project::getAssetsPath() . '/css/parent-progress.css'],
];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
sl_page_title($className, [['Classes', './classes'], ['Class Details', './class-details?class-id=' . $classId], ['Class Payments', '']], '', '<a class="custom-outline-btn" href="./payments?child=' . $childId . '">' . enlixo_icon('payments', 18) . 'All Payments</a>');
parent_child_switch($children, $childId, './class-payments?class-id=' . $classId . '&child=');
sf_render($rows, 'Fees of this class appear here once the tutor sets up its payment plan.');
include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
