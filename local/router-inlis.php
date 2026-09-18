<?php
// ponytail: dev-only router for `php -S`. Production uses Apache + public/.htaccess.
$public = dirname(__DIR__) . '/INLISLIteV33/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$candidate = $public . $path;
if ($path !== '/' && is_file($candidate)) {
    return false;
}
require $public . '/index.php';
