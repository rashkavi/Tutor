<?php
// Local dev server that behaves like the Apache .htaccess rules:
//   php -S 127.0.0.1:8080 bin/dev-router.php      (run from the repo root)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__);

if (str_starts_with($path, '/api/v1/')) {
    require $root . '/api/v1/index.php';
    return true;
}
if (is_file($root . $path)) {
    return false; // static file or .php as-is
}
if (is_dir($root . $path)) {
    foreach (['index.php', 'index.html'] as $index) {
        if (is_file($root . rtrim($path, '/') . '/' . $index)) {
            chdir($root . rtrim($path, '/'));
            require $root . rtrim($path, '/') . '/' . $index;
            return true;
        }
    }
}
if (is_file($root . $path . '.php')) { // extensionless URLs like /tutor/students
    chdir(dirname($root . $path . '.php'));
    require $root . $path . '.php';
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
