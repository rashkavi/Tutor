<?php
/**
 * Connecting a tutor's own Zoom (OAuth "user" app), used by tutor/zoom-connect.php and /zoom-callback.php.
 * Client id and secret come from config/config.php (ZOOM_OAUTH_CLIENT_ID / ZOOM_OAUTH_CLIENT_SECRET in the environment
 * or config/config.local.php), never from the code. The redirect address is this site's address + /zoom-callback.php;
 * add exactly that address to the Zoom app's "Redirect URL for OAuth" and "Add allow lists".
 */
require_once dirname(__DIR__, 2) . '/src/Legacy/Bridge.php';

if (!function_exists('enlixo_zoom_oauth')) {
    /** @return array{client_id:string, client_secret:string, redirect:string} */
    function enlixo_zoom_oauth(): array
    {
        $app = \Enlixo\Legacy\Bridge::app();
        $cfg = $app?->config['zoom'] ?? [];
        $base = rtrim((string) (getenv('APP_URL') ?: Project::getBaseUrl()), '/');
        return [
            'client_id' => (string) ($cfg['oauth_client_id'] ?? ''),
            'client_secret' => (string) ($cfg['oauth_client_secret'] ?? ''),
            'redirect' => $base . '/zoom-callback.php',
        ];
    }

    /** The tutor's own Zoom integration row: the one asked for when it is theirs, else their first one, else a new one. */
    function enlixo_zoom_integration(int $tutorId, int $askedId = 0): int
    {
        if ($askedId > 0) {
            $r = Database::search("SELECT integration_id FROM integrations WHERE integration_id = '" . $askedId . "' AND users_user_id = '" . $tutorId . "' LIMIT 1");
            if ($r && $r->num_rows) {
                return $askedId;
            }
            return 0; // someone else's integration: never connect to it
        }
        $r = Database::search("SELECT integration_id FROM integrations WHERE users_user_id = '" . $tutorId . "' AND integration_type_integration_type_id = 1 ORDER BY integration_id LIMIT 1");
        if ($r && $r->num_rows) {
            return (int) $r->fetch_assoc()['integration_id'];
        }
        Database::iud("INSERT INTO integrations (name, short_description, integration_type_integration_type_id, created_at, status_status_id, users_user_id)
                       VALUES ('Zoom', 'Connect Zoom to host your online classes', 1, NOW(), 1, '" . $tutorId . "')");
        $r = Database::search("SELECT MAX(integration_id) AS id FROM integrations WHERE users_user_id = '" . $tutorId . "'");
        return (int) ($r->fetch_assoc()['id'] ?? 0);
    }

    /** Message for ?zoom=<code> on the Integrations page. */
    function enlixo_zoom_message(string $code): ?array
    {
        return [
            'connected' => ['ok', 'Zoom is connected. Online class days now get their Zoom meeting by themselves.'],
            'not_setup' => ['bad', 'Zoom connection is not set up on this server yet. Please tell ENLIXO support (Zoom app keys missing).'],
            'denied' => ['bad', 'Zoom was not connected: the permission was not given on the Zoom page. Try again and press Allow.'],
            'expired' => ['bad', 'The Zoom sign-in took too long or was opened in another browser. Please press Connect Zoom again.'],
            'token' => ['bad', 'Zoom did not accept the connection. Please try again; if it keeps failing, tell ENLIXO support.'],
            'plan' => ['bad', 'Zoom integration comes with STARTER. On FREE, paste the Zoom link for each class day.'],
            'not_yours' => ['bad', 'That integration does not belong to your account.'],
        ][$code] ?? null;
    }
}
