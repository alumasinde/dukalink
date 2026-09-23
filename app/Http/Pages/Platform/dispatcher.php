<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Support\PlatformAuth;
new App();
$requested = strtolower(trim((string)($_GET['admin_link'] ?? ''), '/'));
$configured = trim(PlatformAuth::basePath(), '/');
if ($requested === '' || !hash_equals($configured, $requested)) {
    http_response_code(404);
    require $root . '/404.php';
    exit;
}
$action = (string)($_GET['action'] ?? 'login');
$files = ['login'=>'login.php','logout'=>'logout.php','dashboard'=>'index.php','plans'=>'plans.php','merchants'=>'merchants.php','shops'=>'shops.php','orders'=>'orders.php','subscriptions'=>'subscriptions.php','payments'=>'payments.php','users'=>'users.php','audit'=>'audit.php','merchant-view'=>'merchant.php','shop-view'=>'shop.php'];
if (!isset($files[$action])) { http_response_code(404); require $root . '/404.php'; exit; }
require __DIR__ . '/' . $files[$action];
