<?php
// Parent → a class of a child (parent/class-details.php?class-id=[&child=]): the same class page the student sees
// (includes/enlixo/class-overview.php), for one of the parent's children in that class; a switch when two are in it.
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
require_once __DIR__ . '/../includes/enlixo/class-overview.php';
$parentId = (int) $sessionUserObject['user_id'];
$classId = (int) ($_GET['class-id'] ?? 0);
$inClass = ps_class_children($parentId, $classId);
if (!$inClass) {
    header("Location: ./classes");
    exit;
}
$children = ps_children($parentId);
$childId = in_array((int) ($_GET['child'] ?? 0), $inClass, true) ? (int) $_GET['child'] : $inClass[0];
$o = co_overview($childId, $classId);
if (!$o) {
    header("Location: ./classes");
    exit;
}
$child = $children[$childId] ?? ['first_name' => 'Your child', 'name' => 'Your child'];
$lf = ['title' => $o['name'], 'menu' => 'Classes', 'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php', 'js' => []];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
$switch = '';
if (count($inClass) > 1) {
    foreach ($inClass as $id) {
        $switch .= '<a class="' . ($id === $childId ? 'custom-btn' : 'custom-outline-btn') . '" href="./class-details?class-id=' . $classId . '&child=' . $id . '">' . sl_e($children[$id]['first_name'] ?? 'Child') . '</a>';
    }
}
sl_page_title($o['name'], [['Classes', './classes'], [$child['name'], './child-classes?child-id=' . $childId], ['Class Details', null]], '',
    $switch . '<a class="custom-btn" href="./class-payments?class-id=' . $classId . '&child=' . $childId . '">' . enlixo_icon('payments', 18) . 'Payments</a>');
co_render($o, [
    'timetable' => './class-time-table?class-id=' . $classId, 'attendance' => './class-attendance?class-id=' . $classId,
    'recordings' => './class-recordings?class-id=' . $classId, 'pre' => './class-pre-recordings?class-id=' . $classId,
    'tutes' => './tutes', 'payments' => './class-payments?class-id=' . $classId . '&child=' . $childId,
    'day' => './class-day?time-table-id=', 'recording' => './watch-recording?recording-id=', 'tute' => null,
], ['who' => $child['first_name']]);
include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
