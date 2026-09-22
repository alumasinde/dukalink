<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in development server.
 *
 * Apache/XAMPP uses public/.htaccess.
 * `php -S localhost:8000 -t public public/router.php`
 * uses this file so the same clean URLs work locally.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = trim($uri, '/');

if ($path !== '') {
    $candidate = __DIR__ . '/' . $path;

    if (is_file($candidate)) {
        return false;
    }
}

$routes = [
    '' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'onboarding' => 'onboarding.php',
    'onboarding/complete' => 'onboarding-complete.php',

    'dashboard' => 'dashboard/index.php',
    'products' => 'products/index.php',
    'products/create' => 'products/create.php',
    'products/edit' => 'products/edit.php',
    'categories' => 'categories/index.php',

    'settings' => 'settings/index.php',
    'settings/shop' => 'settings/shop.php',

    'cart' => 'cart/index.php',
    'checkout' => 'checkout/index.php',
    'track' => 'track/index.php',
];

if (isset($routes[$path])) {
    require __DIR__ . '/' . $routes[$path];
    return true;
}

if (preg_match('#^shop/([a-zA-Z0-9][a-zA-Z0-9_-]{1,79})$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/shop/index.php';
    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
