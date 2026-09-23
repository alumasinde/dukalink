<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
new \App\Bootstrap\App();

/*
 * Dukame development router.
 *
 * Run:
 *   php -S localhost:8000 -t public public/router.php
 *
 * Apache/XAMPP uses public/.htaccess instead.
 */

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

// Normalize duplicate/trailing slashes without touching query parameters.
$path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '/', '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$route = trim($path, '/');

if ($route === 'robots.txt') { require __DIR__ . '/seo/robots.php'; return true; }

// Platform administration is intentionally namespaced by ADMIN_LINK. The legacy /admin path is not registered.
$adminLink = trim((string)($_ENV['ADMIN_LINK'] ?? 'platform'), '/');
$adminLink = preg_replace('/[^a-zA-Z0-9_-]/', '', $adminLink) ?: 'platform';
$adminLink = strtolower($adminLink);
// Platform Admin root and actions. Only the configured ADMIN_LINK is valid.
// Example: ADMIN_LINK=platform -> /platform and /platform/login.
// /admin, /admin/login and other unconfigured prefixes are never registered.
if ($route === $adminLink) {
    $_GET['admin_link'] = $adminLink;
    $_GET['action'] = 'dashboard';
    require __DIR__ . '/platform/dispatcher.php';
    return true;
}

if (preg_match('#^([a-z0-9_-]+)/(merchants|shops)/(?:view/)?([0-9]+)$#i', $route, $m)) {
    if (strtolower($m[1]) !== $adminLink) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return true;
    }
    $_GET['admin_link'] = strtolower($m[1]);
    $_GET['action'] = strtolower($m[2]) === 'merchants' ? 'merchant-view' : 'shop-view';
    $_GET['id'] = (int)$m[3];
    require __DIR__ . '/platform/dispatcher.php';
    return true;
}

if (preg_match('#^([a-z0-9_-]+)/(login|logout|dashboard|plans|merchants|shops|orders|subscriptions|payments|users|audit)$#i', $route, $m)) {
    if (strtolower($m[1]) !== $adminLink) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return true;
    }
    $_GET['admin_link'] = strtolower($m[1]);
    $_GET['action'] = strtolower($m[2]);
    require __DIR__ . '/platform/dispatcher.php';
    return true;
}

if ($route === 'sitemap.xml') { require __DIR__ . '/seo/sitemap.php'; return true; }

if ($route === 'api/v1' || str_starts_with($route, 'api/v1/')) {
    require __DIR__ . '/api.php';
    return true;
}

// Let the built-in server serve real public files (CSS, JS, images, uploads, etc.).
if ($path !== '/') {
    $candidate = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($candidate)) {
        return false;
    }
}

$routes = [
    '' => 'index.php',
    'login' => 'login.php',
    'logout' => 'logout.php',
    'register' => 'register.php',
    'onboarding' => 'onboarding.php',
    'onboarding/complete' => 'onboarding-complete.php',

    'dashboard' => 'dashboard/index.php',
    'products' => 'products/index.php',
    'products/create' => 'products/create.php',
    'products/edit' => 'products/edit.php',
    'categories' => 'categories/index.php',
    'orders' => 'orders/index.php',
    'orders/view' => 'orders/view.php',
    'customers' => 'customers/index.php',
    'customers/view' => 'customers/view.php',

    'settings' => 'settings/index.php',
    'settings/shop' => 'settings/shop.php',
    'settings/payments' => 'settings/payments.php',
    'settings/delivery' => 'settings/delivery.php',
    'settings/notifications' => 'settings/notifications.php',
    'subscription' => 'subscription/index.php',
    'subscription/pay' => 'subscription/pay.php',
    'subscription/status' => 'subscription/status.php',

    'cart' => 'cart/index.php',
    'checkout' => 'checkout/index.php',
    'track' => 'track/index.php',
];

if (isset($routes[$route])) {
    require __DIR__ . '/' . $routes[$route];
    return true;
}

// Legacy public shop URL. Redirect to the short root-level URL.
if (preg_match('#^shop/([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    header('Location: /' . strtolower($m[1]), true, 301);
    return true;
}

// Public product URL:
//   /merchant-slug/product/product-slug
if (preg_match('#^([a-z0-9]+(?:-[a-z0-9]+)*)/product/([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    $_GET['slug'] = strtolower($m[1]);
    $_GET['product'] = strtolower($m[2]);
    require __DIR__ . '/shop/product.php';
    return true;
}

// Public merchant storefront:
//   /merchant-slug
//
// This MUST come after all reserved application routes above. That prevents
// /login, /products, /cart, /api, etc. from being interpreted as shop slugs.
if (preg_match('#^([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    $_GET['slug'] = strtolower($m[1]);
    require __DIR__ . '/shop/index.php';
    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
