<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";

session_start();

if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: integrations.php?error=invalid_integration");
    exit;
}

$integrationId = (int) $_GET['id'];

$clientId = "BVj_HavbTf2ZdI1IWnt5Q";
$redirectUri = "https://enlixotutor.com/zoom-callback.php";

$csrfToken = bin2hex(random_bytes(16));

$_SESSION['zoom_oauth_state_token'] = $csrfToken;
$_SESSION['zoom_oauth_integration_id'] = $integrationId;

$statePayload = json_encode([
    'token' => $csrfToken,
    'integration_id' => $integrationId
]);

$state = rtrim(strtr(base64_encode($statePayload), '+/', '-_'), '=');

$authUrl = "https://zoom.us/oauth/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'state' => $state
]);

header("Location: " . $authUrl);
exit;