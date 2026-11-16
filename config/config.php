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
    // Message Center (src/Messaging). Secrets come from the environment (or config.local.php), never from the code.
    'messaging' => [
        // 'smtp' sends for real; 'log' writes every e-mail / WhatsApp to storage/messages.log (tests, local copies)
        'mail_driver' => getenv('MAIL_DRIVER') ?: 'smtp',
        'whatsapp_driver' => getenv('WHATSAPP_DRIVER') ?: 'cloud',
        'mail' => [
            'host' => getenv('MAIL_HOST') ?: '', 'port' => (int) (getenv('MAIL_PORT') ?: 465),
            'username' => getenv('MAIL_USERNAME') ?: '', 'password' => getenv('MAIL_PASSWORD') ?: '',
            'from' => getenv('MAIL_FROM_ADDRESS') ?: '', 'from_name' => getenv('MAIL_FROM_NAME') ?: 'ENLIXO Tutor',
        ],
        'whatsapp' => [
            // ENLIXO's own WhatsApp Cloud API number (the "ENLIXO number" way of sending)
            'token' => getenv('WHATSAPP_TOKEN') ?: '',
            'phone_number_id' => getenv('WHATSAPP_PHONE_ID') ?: '470586202805184',
            'notice_template' => getenv('WHATSAPP_NOTICE_TEMPLATE') ?: 'enlixo_class_notice',
            'notice_lang' => getenv('WHATSAPP_NOTICE_LANG') ?: 'en',
            'graph_version' => 'v22.0',
            // Meta Embedded Signup ("Connect with Facebook" for a tutor's own number). Empty = only the manual form.
            'meta_app_id' => getenv('META_APP_ID') ?: '',
            'meta_app_secret' => getenv('META_APP_SECRET') ?: '',
            'embedded_config_id' => getenv('META_ES_CONFIG_ID') ?: '',
        ],
        'batch' => 25, // recipients sent per request
    ],
    'ai' => [
        // Google AI Studio key for "Generate thumbnail" on the class form. Empty = the button makes a simple cover in the browser instead.
        'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
        'gemini_image_model' => getenv('GEMINI_IMAGE_MODEL') ?: 'gemini-2.5-flash-image',
        'thumbnails_per_day' => 10, // per tutor
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
