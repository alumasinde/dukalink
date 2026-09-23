<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);
$path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? '/', '/');
$path = $path === '/' ? '/' : rtrim($path, '/');

// Let PHP's built-in server serve existing static files directly.
// This avoids booting the application for CSS, JS, images, etc.
$staticFile = __DIR__ . $path;
if ($path !== '/' && is_file($staticFile)) {
    return false;
}

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;

new App();

$route = trim($path, '/');

$webRoutes = require $root . '/routes/web.php';
$platformRoutes = require $root . '/routes/platform.php';
$apiRoutes = require $root . '/routes/api.php';

$notFound = static function () use ($root): void {
    http_response_code(404);
    require $root . '/public/404.php';
};

// Lightweight production health check. Never expose exception details.
if ($route === 'health') {
    require $root . '/public/health.php';
    return true;
}

// SEO endpoints.
if ($route === 'robots.txt') {
    require $root . '/app/Http/Pages/Seo/robots.php';
    return true;
}

if ($route === 'sitemap.xml') {
    require $root . '/app/Http/Pages/Seo/sitemap.php';
    return true;
}

// API v1 has its own router/controllers.
if ($route === $apiRoutes['prefix'] || str_starts_with($route, $apiRoutes['prefix'] . '/')) {
    require $root . '/' . $apiRoutes['entrypoint'];
    return true;
}

// Platform Admin namespace is configurable through ADMIN_LINK.
$adminLink = trim((string)($_ENV['ADMIN_LINK'] ?? 'platform'), '/');
$adminLink = preg_replace('/[^a-zA-Z0-9_-]/', '', $adminLink) ?: 'platform';
$adminLink = strtolower($adminLink);

if ($route === $adminLink) {
    $_GET['admin_link'] = $adminLink;
    $_GET['action'] = 'dashboard';
    require $root . '/app/Http/Pages/Platform/dispatcher.php';
    return true;
}

if (preg_match('#^([a-z0-9_-]+)/(merchants|shops)/(?:view/)?([0-9]+)$#i', $route, $m)) {
    if (strtolower($m[1]) !== $adminLink) {
        $notFound();
        return true;
    }

    $section = strtolower($m[2]);
    if (!isset($platformRoutes['detail'][$section])) {
        $notFound();
        return true;
    }

    $_GET['admin_link'] = strtolower($m[1]);
    $_GET['action'] = $platformRoutes['detail'][$section];
    $_GET['id'] = (int)$m[3];
    require $root . '/app/Http/Pages/Platform/dispatcher.php';
    return true;
}

if (preg_match('#^([a-z0-9_-]+)/(login|logout|dashboard|plans|merchants|shops|orders|subscriptions|payments|users|audit)$#i', $route, $m)) {
    if (strtolower($m[1]) !== $adminLink || !in_array(strtolower($m[2]), $platformRoutes['simple'], true)) {
        $notFound();
        return true;
    }

    $_GET['admin_link'] = strtolower($m[1]);
    $_GET['action'] = strtolower($m[2]);
    require $root . '/app/Http/Pages/Platform/dispatcher.php';
    return true;
}

// Legacy /shop/{slug} links permanently redirect to root-level stores.
if (preg_match('#^shop/([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    header('Location: /' . strtolower($m[1]), true, 301);
    return true;
}

if ($route === 'shop') {
    $notFound();
    return true;
}

// Fixed application routes.
if (isset($webRoutes[$route])) {
    require $root . '/' . $webRoutes[$route];
    return true;
}

// Dynamic customer product route.
if (preg_match('#^([a-z0-9]+(?:-[a-z0-9]+)*)/product/([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    $_GET['slug'] = strtolower($m[1]);
    $_GET['product'] = strtolower($m[2]);
    require $root . '/app/Http/Pages/Customer/shop/product.php';
    return true;
}

// Dynamic root-level merchant store route.
if (preg_match('#^([a-z0-9]+(?:-[a-z0-9]+)*)$#i', $route, $m)) {
    $_GET['slug'] = strtolower($m[1]);
    require $root . '/app/Http/Pages/Customer/shop/index.php';
    return true;
}

$notFound();
return true;
