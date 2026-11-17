<?php
// Tutor → Integrations → Connect Zoom: sends the tutor to Zoom to allow ENLIXO Tutor, then Zoom comes back to
// /zoom-callback.php. Only the tutor's own integration (made here if they have none), only on a plan with
// Zoom integration (STARTER and up). Keys and the return address: includes/enlixo/zoom-oauth.php.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/zoom-oauth.php";

if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
    exit;
}
$tutor = SessionUser::getCurrentUserObject('Tutor');
$tutorId = (int) ($tutor['user_id'] ?? 0);
$back = static function (string $code): void {
    header('Location: intergrations?zoom=' . $code);
    exit;
};

$app = \Enlixo\Legacy\Bridge::app();
if ($app !== null && !$app->entitlements()->has($tutorId, 'zoom.tutor_integration')) {
    $back('plan');
}
$oauth = enlixo_zoom_oauth();
if ($oauth['client_id'] === '' || $oauth['client_secret'] === '') {
    $back('not_setup');
}
$integrationId = enlixo_zoom_integration($tutorId, (int) ($_GET['id'] ?? 0));
if ($integrationId === 0) {
    $back('not_yours');
}

$token = bin2hex(random_bytes(16));
$_SESSION['zoom_oauth_state_token'] = $token;
$_SESSION['zoom_oauth_integration_id'] = $integrationId;
$state = rtrim(strtr(base64_encode((string) json_encode(['token' => $token, 'integration_id' => $integrationId])), '+/', '-_'), '=');

header('Location: https://zoom.us/oauth/authorize?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $oauth['client_id'],
    'redirect_uri' => $oauth['redirect'],
    'state' => $state,
]));
exit;
