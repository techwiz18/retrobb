<?php
declare(strict_types=1);
// PHP built-in server router: serve real files (css/js), else boot index.php.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false; // let the server serve it
    }
}
require __DIR__ . '/index.php';
