<?php
use function App\Support\e;
use function App\Support\asset_url;
use App\Support\Csrf;
$title = $title ?? 'Platform';
$content = $content ?? '';
$platformUser = $platformUser ?? null;
$platformSection = $platformSection ?? 'overview';
$brand = require dirname(__DIR__, 3) . '/config/branding.php';
$base = \App\Support\PlatformAuth::basePath();
$themeStyle = '--dk-primary:' . e($brand['primary']) . ';--dk-accent:' . e($brand['accent']) . ';--dk-primary-dark:color-mix(in srgb,var(--dk-primary) 82%,#000);--dk-primary-soft:color-mix(in srgb,var(--dk-primary) 10%,#fff);--dk-accent-soft:color-mix(in srgb,var(--dk-accent) 10%,#fff);';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($brand['name']) ?> Platform</title>
<meta name="robots" content="noindex,nofollow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/variables.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/pages/platform.css')) ?>">
<style>:root{<?= $themeStyle ?>}</style>
</head>
<body class="platform-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="platform-shell">
<aside class="platform-sidebar">
    <a class="platform-brand" href="<?= e($base) ?>"><span class="brand-mark">D</span><span><strong><?= e($brand['name']) ?></strong><small>Platform</small></span></a>
    <div class="platform-admin-card"><div class="platform-avatar"><?= e(strtoupper(substr((string)($platformUser['first_name'] ?? 'A'),0,1))) ?></div><div class="min-w-0"><strong class="d-block text-truncate"><?= e(trim(($platformUser['first_name'] ?? '') . ' ' . ($platformUser['last_name'] ?? '')) ?: 'Administrator') ?></strong><small><?= e(ucwords(str_replace('_',' ',(string)($platformUser['role'] ?? 'admin')))) ?></small></div></div>
    <div class="platform-nav-label">Overview</div>
    <nav class="platform-nav">
      <a class="<?= $platformSection==='overview'?'active':'' ?>" href="<?= e($base) ?>"><span>⌂</span> Dashboard</a>
    </nav>
    <div class="platform-nav-label">Business</div>
    <nav class="platform-nav">
      <a class="<?= $platformSection==='merchants'?'active':'' ?>" href="<?= e($base) ?>/merchants"><span>♙</span> Merchants</a>
      <a class="<?= $platformSection==='shops'?'active':'' ?>" href="<?= e($base) ?>/shops"><span>▣</span> Shops</a>
      <a class="<?= $platformSection==='orders'?'active':'' ?>" href="<?= e($base) ?>/orders"><span>▤</span> Orders</a>
    </nav>
    <div class="platform-nav-label">Billing</div>
    <nav class="platform-nav">
      <a class="<?= $platformSection==='subscriptions'?'active':'' ?>" href="<?= e($base) ?>/subscriptions"><span>◉</span> Subscriptions</a>
      <a class="<?= $platformSection==='plans'?'active':'' ?>" href="<?= e($base) ?>/plans"><span>◇</span> Plans</a>
      <a class="<?= $platformSection==='payments'?'active':'' ?>" href="<?= e($base) ?>/payments"><span>₵</span> Payments</a>
    </nav>
    <div class="platform-nav-label">Administration</div>
    <nav class="platform-nav">
      <a class="<?= $platformSection==='users'?'active':'' ?>" href="<?= e($base) ?>/users"><span>♙</span> Platform Users</a>
      <a class="<?= $platformSection==='audit'?'active':'' ?>" href="<?= e($base) ?>/audit"><span>◌</span> Audit Log</a>
    </nav>
    <div class="platform-sidebar-bottom"><form method="post" action="<?= e($base) ?>/logout" class="m-0"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="platform-nav-link border-0 bg-transparent w-100 text-start" type="submit"><span>↪</span> Sign out</button></form></div>
</aside>
<div class="platform-main">
<header class="platform-topbar"><div><div class="platform-kicker">Dukame platform</div><h1><?= e($title) ?></h1></div><div class="d-flex align-items-center gap-2"><a class="btn btn-sm btn-outline-dark" href="/" target="_blank">View site</a><a class="btn btn-sm btn-primary" href="<?= e($base) ?>/plans">Plans</a></div></header>
<main id="main-content" class="platform-content"><?= $content ?></main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= e(asset_url('/assets/js/app.js')) ?>"></script>
</body></html>
