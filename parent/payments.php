<?php
// Payments (parent): the class fees of one child (chips switch the child), same layout as the student portal
// Payments page (includes/enlixo/student-fees.php). A parent only sees their own children (parent-scope.php).
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
$children = ps_children((int) $sessionUserObject['user_id']);
$childId = (int) ($_GET['child'] ?? 0);
if (!isset($children[$childId])) {
    $childId = (int) (array_key_first($children) ?? 0);
}
$rows = $childId ? sf_fees($childId) : [];

$lf = [
    'title' => 'Payments', 'menu' => 'Payments',
    'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php',
    'css' => [Project::getAssetsPath() . '/css/student-fees.css', Project::getAssetsPath() . '/css/parent-progress.css'],
];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
sl_page_title('Payments', [], "Your child's class fees, discounts and slips. Pay a fee by uploading the bank slip; the tutor checks it.");
parent_child_switch($children, $childId, './payments?child=');
if ($childId && count($children) > 1) {
    echo '<p class="text-14 sub-text mb-3">Fees of <strong class="main-text">' . sl_e($children[$childId]['name']) . '</strong></p>';
}
sf_render($rows, $children ? 'Fees appear here once the tutor sets up the payment plan of the class.' : 'No child is linked to your number yet. Ask the tutor to add your WhatsApp number to your child.');
include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
