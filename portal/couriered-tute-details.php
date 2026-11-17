<?php
// Student → My Tute Parcels: every printed tute posted to the student and how far it has got
// (Enlixo\Tutes\TuteRepository::forStudent, cards from includes/enlixo/parcels-page.php). Same frame as Tutes.
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/parcels-page.php';
$parcels = [];
try {
    require_once __DIR__ . '/../src/bootstrap.php';
    $app = \Enlixo\Legacy\Bridge::app();
    if ($app) {
        $parcels = (new \Enlixo\Tutes\TuteRepository($app->db()))->forStudent((int) $sessionUserObject['student_id']);
    }
} catch (\Throwable $e) {
    error_log('Tute parcels: ' . $e->getMessage());
}
$lf = ['title' => 'My Tute Parcels', 'menu' => 'Tutes'];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title('My Tute Parcels', [['Tutes', './tutes'], ['My Tute Parcels', null]], '',
    '<a class="custom-outline-btn" href="./my-profile">' . enlixo_icon('location', 18) . 'My Address</a>');
pc_render($parcels, ['tute' => static fn ($p) => './downloadable-tute?class-id=' . $p['class_id'] . '&tute-id=' . $p['tute_id']]);
include __DIR__ . '/includes/learning-frame-bottom.php';
