<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";

if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
$userId = (int) $sessionUserObject["user_id"];

if (!isset($_GET['code'], $_GET['state'])) {
    header("Location: tutor/integrations.php?error=zoom_auth_failed");
    exit;
}

$authorizationCode = $_GET['code'];
$returnedState = $_GET['state'];

$decodedState = base64_decode(strtr($returnedState, '-_', '+/'));
$stateData = json_decode($decodedState, true);

if (
    !$stateData ||
    !isset($stateData['token'], $stateData['integration_id']) ||
    !isset($_SESSION['zoom_oauth_state_token'], $_SESSION['zoom_oauth_integration_id'])
) {
    header("Location: tutor/integrations.php?error=invalid_state");
    exit;
}

if (
    $stateData['token'] !== $_SESSION['zoom_oauth_state_token'] ||
    (int)$stateData['integration_id'] !== (int)$_SESSION['zoom_oauth_integration_id']
) {
    header("Location: tutor/integrations.php?error=state_mismatch");
    exit;
}

$integrationId = (int) $stateData['integration_id'];

unset($_SESSION['zoom_oauth_state_token'], $_SESSION['zoom_oauth_integration_id']);

$CLIENT_ID = "BVj_HavbTf2ZdI1IWnt5Q";
$CLIENT_SECRET = "Bg4udnjPCW1lM61uaj8lZM0NogXcVCiu";
$REDIRECT_URI = "https://enlixotutor.com/zoom-callback.php";

$tokenUrl = "https://zoom.us/oauth/token";

$postData = http_build_query([
    "grant_type" => "authorization_code",
    "code" => $authorizationCode,
    "redirect_uri" => $REDIRECT_URI
]);

$headers = [
    "Authorization: Basic " . base64_encode($CLIENT_ID . ":" . $CLIENT_SECRET),
    "Content-Type: application/x-www-form-urlencoded"
];

$ch = curl_init($tokenUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if (!empty($curlError)) {
    header("Location: tutor/integrations.php?error=zoom_curl_error");
    exit;
}

if ($httpCode !== 200) {
    header("Location: tutor/integrations.php?error=zoom_token_error");
    exit;
}

$tokenData = json_decode($response, true);

if (
    !isset($tokenData['access_token']) ||
    !isset($tokenData['refresh_token']) ||
    !isset($tokenData['expires_in'])
) {
    header("Location: tutor/intergrations.php?error=zoom_invalid_token_response");
    exit;
}

$accessToken = Database::escape($tokenData['access_token']);
$refreshToken = Database::escape($tokenData['refresh_token']);
$expiresIn = (int) $tokenData['expires_in'];
$tokenExpiresAt = date("Y-m-d H:i:s", time() + $expiresIn);

$existing = Database::search("
    SELECT `integration_connection_id`
    FROM `integration_connections`
    
    WHERE  `integration_id` = '{$integrationId}'
      AND `provider` = 'zoom'
    LIMIT 1
");

if ($existing->num_rows > 0) {
    Database::iud("
        UPDATE `integration_connections`
        SET
            `access_token` = '{$accessToken}',
            `refresh_token` = '{$refreshToken}',
            `token_expires_at` = '{$tokenExpiresAt}',
            `connected_at` = NOW()
        WHERE `integration_id` = '{$integrationId}'
          AND `provider` = 'zoom'
    ");
} else {
    Database::iud("
        INSERT INTO `integration_connections`
            (`integration_id`, `provider`, `access_token`, `refresh_token`, `token_expires_at`, `connected_at`)
        VALUES
            ('{$integrationId}', 'zoom', '{$accessToken}', '{$refreshToken}', '{$tokenExpiresAt}', NOW())
    ");
}

header("Location: tutor/intergrations.php?success=zoom_connected");
exit;
