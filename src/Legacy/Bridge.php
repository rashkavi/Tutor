<?php
declare(strict_types=1);

namespace Enlixo\Legacy;

use Enlixo\App;

/**
 * Lets the existing PHP pages (tutor/, portal/, parent/) use the new services.
 * Returns null when the new code isn't configured yet (no config/config.local.php or DB_* env),
 * so a half-finished deploy never takes a page down.
 */
final class Bridge
{
    private static ?App $app = null;
    private static bool $tried = false;

    public static function app(): ?App
    {
        if (!self::$tried) {
            self::$tried = true;
            try {
                $config = require dirname(__DIR__) . '/bootstrap.php';
                $app = new App($config, $_SERVER['REMOTE_ADDR'] ?? null);
                $app->db()->pdo(); // fail fast if the DB settings are wrong
                self::$app = $app;
            } catch (\Throwable $e) {
                error_log('ENLIXO bridge disabled: ' . $e->getMessage());
            }
        }
        return self::$app;
    }
}
