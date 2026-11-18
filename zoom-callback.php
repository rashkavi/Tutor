<?php
// Zoom comes back here after the tutor allowed ENLIXO Tutor (tutor/zoom-connect.php). Checks the state, swaps the code
// for tokens and saves them on the tutor's own integration, then returns to Tutor → Integrations with a message.
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
require_once "./includes/enlixo/zoom-oauth.php";

if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./tutor/sign-in");
    exit;
}
$tutor = SessionUser::getCurrentUserObject('Tutor');
$tutorId = (int) ($tutor['user_id'] ?? 0);
$back = static function (string $code, string $why = ''): void {
    header('Location: tutor/intergrations?zoom=' . $code . ($why !== '' ? '&why=' . rawurlencode(substr($why, 0, 120)) : ''));
    exit;
};

if (isset($_GET['error'])) {
    $back($_GET['error'] === 'access_denied' ? 'denied' : 'token', (string) ($_GET['error_description'] ?? $_GET['error'])); // Decline, or Zoom refused the app
}
if (!isset($_GET['code'], $_GET['state'])) {
    $back('expired');
}
$state = json_decode((string) base64_decode(strtr((string) $_GET['state'], '-_', '+/')), true);
$expected = $_SESSION['zoom_oauth_state_token'] ?? null;
$integrationId = (int) ($_SESSION['zoom_oauth_integration_id'] ?? 0);
unset($_SESSION['zoom_oauth_state_token'], $_SESSION['zoom_oauth_integration_id']);
if (!is_array($state) || !$expected || !hash_equals((string) $expected, (string) ($state['token'] ?? '')) || (int) ($state['integration_id'] ?? 0) !== $integrationId) {
    $back('expired');
}
if (enlixo_zoom_integration($tutorId, $integrationId) === 0) {
    $back('not_yours');
}

$oauth = enlixo_zoom_oauth();
if ($oauth['client_id'] === '' || $oauth['client_secret'] === '') {
    $back('not_setup');
}
$ch = curl_init('https://zoom.us/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($oauth['client_id'] . ':' . $oauth['client_secret']), 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'authorization_code', 'code' => (string) $_GET['code'], 'redirect_uri' => $oauth['redirect']]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
$token = json_decode((string) $response, true);
if ($curlError !== '' || $httpCode !== 200 || !isset($token['access_token'], $token['refresh_token'], $token['expires_in'])) {
    error_log('Zoom token exchange failed: HTTP ' . $httpCode . ' ' . $curlError . ' ' . substr((string) $response, 0, 300) . ' redirect=' . $oauth['redirect']);
    $back('token', $curlError !== '' ? 'Could not reach Zoom from the server' : (string) ($token['reason'] ?? $token['error'] ?? ('HTTP ' . $httpCode)));
}

$access = Database::escape((string) $token['access_token']);
$refresh = Database::escape((string) $token['refresh_token']);
$expires = date('Y-m-d H:i:s', time() + (int) $token['expires_in']);
$existing = Database::search("SELECT integration_connection_id FROM integration_connections WHERE integration_id = '" . $integrationId . "' AND provider = 'zoom' LIMIT 1");
if ($existing && $existing->num_rows > 0) {
    Database::iud("UPDATE integration_connections SET access_token = '$access', refresh_token = '$refresh', token_expires_at = '$expires', connected_at = NOW()
                    WHERE integration_id = '" . $integrationId . "' AND provider = 'zoom'");
} else {
    Database::iud("INSERT INTO integration_connections (integration_id, provider, access_token, refresh_token, token_expires_at, connected_at)
                   VALUES ('" . $integrationId . "', 'zoom', '$access', '$refresh', '$expires', NOW())");
}
$back('connected');
