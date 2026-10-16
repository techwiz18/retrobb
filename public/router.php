<?php
declare(strict_types=1);
// PHP built-in server router: serve real files (css/js), else boot index.php.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false; // let the server serve it
    }
    // Direct subpath access (pre-cutover testing without the proxy): if the
    // request isn't a file but dropping one leading segment hits one, serve
    // that — mirrors the public/.htaccess rules (including the package-root
    // layout, where real files live one level down under public/). Safe: app
    // routes never collide with real filenames (slugs are sanitized, suffixed
    // with .t/.f/.u).
    if ($path !== '/') {
        $parts = explode('/', ltrim($path, '/'), 2);
        if (isset($parts[1])) {
            foreach ([$parts[1], 'public/' . $parts[1]] as $rel) {
                $alt = realpath(__DIR__ . '/' . $rel);
                if ($alt !== false && str_starts_with($alt, __DIR__) && is_file($alt)) {
                    if (str_ends_with($alt, '.php')) {
                        require $alt;
                        return true;
                    }
                    $mime = [
                        'css' => 'text/css', 'js' => 'application/javascript',
                        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
                        'xml' => 'application/xml', 'xsl' => 'application/xslt+xml', 'txt' => 'text/plain',
                    ][strtolower(pathinfo($alt, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
                    header('Content-Type: ' . $mime);
                    readfile($alt);
                    return true;
                }
            }
        }
    }
}
require_once __DIR__ . '/index.php';
