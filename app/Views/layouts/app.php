<?php
use function App\Support\e;
use function App\Support\asset_url;
$title = $title ?? 'Dukame';
$content = $content ?? '';
$merchantLayout = $merchantLayout ?? false;
$brand = require dirname(__DIR__, 3) . '/config/branding.php';
$themeStyle = '--dk-primary:' . e($brand['primary']) . ';--dk-accent:' . e($brand['accent']) . ';--dk-primary-dark:color-mix(in srgb,var(--dk-primary) 82%,#000);--dk-primary-soft:color-mix(in srgb,var(--dk-primary) 10%,#fff);--dk-accent-soft:color-mix(in srgb,var(--dk-accent) 10%,#fff);';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($brand['name']) ?></title>
    <meta name="description" content="<?= e($brand['tagline']) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/components.css')) ?>">
    <style>:root{<?= $themeStyle ?>}</style>
</head>
<body class="<?= $merchantLayout ? 'merchant-app-body' : '' ?>">
<?php if (!$merchantLayout): ?><?php require __DIR__ . '/../components/navbar.php'; ?><?php endif; ?>
<main><?= $content ?></main>
<?php if (!$merchantLayout): ?><footer class="site-footer"><div class="container py-4 d-flex flex-column flex-md-row justify-content-between gap-2"><span class="small text-secondary">© <?= date('Y') ?> <?= e($brand['name']) ?></span><span class="small text-secondary"><?= e($brand['tagline']) ?></span></div></footer><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('/assets/js/app.js')) ?>"></script>
</body>
</html>
