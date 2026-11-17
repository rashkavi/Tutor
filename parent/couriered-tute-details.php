<?php
// Parent → Tute Parcels: every printed tute posted to the parent's children (only their own children,
// includes/enlixo/parent-scope.php), ?child=<id> for one child. Cards from includes/enlixo/parcels-page.php.
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
require_once '../includes/enlixo/parent-scope.php';
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
require_once __DIR__ . '/../includes/enlixo/parcels-page.php';
$children = ps_children((int) ($sessionUserObject['user_id'] ?? 0));
$only = isset($children[(int) ($_GET['child'] ?? 0)]) ? (int) $_GET['child'] : 0;
$parcels = [];
try {
    require_once __DIR__ . '/../src/bootstrap.php';
    $app = \Enlixo\Legacy\Bridge::app();
    if ($app) {
        $repo = new \Enlixo\Tutes\TuteRepository($app->db());
        foreach ($children as $cid => $c) {
            if ($only && $only !== $cid) {
                continue;
            }
            foreach ($repo->forStudent($cid) as $p) {
                $parcels[] = $p + ['child' => $c['first_name']];
            }
        }
    }
} catch (\Throwable $e) {
    error_log('Parent tute parcels: ' . $e->getMessage());
}
$lf = ['title' => 'Tute Parcels', 'menu' => 'Tute Parcels', 'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php', 'js' => []];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
$switch = '';
if (count($children) > 1) {
    $switch .= '<a class="' . ($only ? 'custom-outline-btn' : 'custom-btn') . '" href="./couriered-tute-details">All children</a>';
    foreach ($children as $cid => $c) {
        $switch .= '<a class="' . ($only === $cid ? 'custom-btn' : 'custom-outline-btn') . '" href="./couriered-tute-details?child=' . $cid . '">' . sl_e($c['first_name']) . '</a>';
    }
}
sl_page_title('Tute Parcels', [], count($children) > 1 ? 'Printed tutes your tutors post to your children, from packing to your door.' : 'Printed tutes the tutor posts to your child, from packing to your door.', $switch);
pc_render($parcels, ['child' => count($children) > 1]);
include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
