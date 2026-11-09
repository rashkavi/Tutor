<?php
// Default config. Copy to config/config.local.php to override on a server (that file is git-ignored).
$config = [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'local',
        'timezone' => 'Asia/Colombo',
        // 32+ random bytes, base64. Used to encrypt Zoom start URLs and tokens.
        'key' => getenv('APP_KEY') ?: '',
    ],
    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=enlixo_tutor;charset=utf8mb4',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'recordings' => [
        'heartbeat_seconds' => 15,      // client sends a heartbeat this often while playing
        'heartbeat_grace_seconds' => 5, // network jitter allowance per heartbeat
        'default_multiplier' => 3.0,    // allowed watch time = duration x this
        'warn_at_ratio' => 0.8,
    ],
    'zoom' => [
        // Server-to-Server OAuth app (ENVIIZ Zoom Account add-on)
        's2s_account_id' => getenv('ZOOM_ACCOUNT_ID') ?: '',
        's2s_client_id' => getenv('ZOOM_CLIENT_ID') ?: '',
        's2s_client_secret' => getenv('ZOOM_CLIENT_SECRET') ?: '',
        // OAuth (user) app used when a tutor connects their own Zoom
        'oauth_client_id' => getenv('ZOOM_OAUTH_CLIENT_ID') ?: '',
        'oauth_client_secret' => getenv('ZOOM_OAUTH_CLIENT_SECRET') ?: '',
        'webhook_secret' => getenv('ZOOM_WEBHOOK_SECRET') ?: '',
        'schedule_weeks_ahead' => 2,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
} elseif (!getenv('DB_DSN')) {
    // No config.local.php yet: reuse the database login the existing app already uses (includes/connection.php),
    // so the new pages work on a fresh XAMPP / Hostinger copy without extra setup.
    $legacy = @file_get_contents(dirname(__DIR__) . '/includes/connection.php');
    if ($legacy && preg_match('/new\s+mysqli\(\s*([\'"])(.*?)\1\s*,\s*([\'"])(.*?)\3\s*,\s*([\'"])(.*?)\5\s*,\s*([\'"])(.*?)\7/s', $legacy, $m)) {
        $config['db'] = [
            'dsn' => sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $m[2], $m[8]),
            'user' => $m[4],
            'password' => $m[6],
        ];
    }
}
return $config;
