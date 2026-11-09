<?php
declare(strict_types=1);

// PSR-4 autoloader for the Enlixo\ namespace. Registered always, because the existing vendor/autoload.php
// (phpspreadsheet, agent, pdfparser) doesn't know about src/ until someone runs `composer dump-autoload`.
if (!defined('ENLIXO_AUTOLOAD')) {
    define('ENLIXO_AUTOLOAD', true);
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Enlixo\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$config = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);
return $config;
