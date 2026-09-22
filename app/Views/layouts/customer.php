<?php
use function App\Support\e;
use function App\Support\asset_url;
use function App\Support\base_url;
$title = $title ?? 'Dukame';
$content = $content ?? '';
$brand = require dirname(__DIR__, 3) . '/config/branding.php';
$seo = $seo ?? [];
$seoTitle = (string)($seo['title'] ?? $title);
$seoDescription = trim((string)($seo['description'] ?? $brand['tagline']));
$seoCanonical = (string)($seo['canonical'] ?? base_url($_SERVER['REQUEST_URI'] ?? '/'));
$seoImage = (string)($seo['image'] ?? '');
$seoType = (string)($seo['type'] ?? 'website');
$seoRobots = (string)($seo['robots'] ?? 'index,follow');
$seoSchema = $seo['schema'] ?? null;
$themeStyle = '--dk-primary:' . e($brand['primary']) . ';--dk-accent:' . e($brand['accent']) . ';';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($seoTitle) ?> · <?= e($brand['name']) ?></title>
    <meta name="description" content="<?= e($seoDescription) ?>">
    <meta name="robots" content="<?= e($seoRobots) ?>">
    <link rel="canonical" href="<?= e($seoCanonical) ?>">
    <meta property="og:type" content="<?= e($seoType) ?>">
    <meta property="og:title" content="<?= e($seoTitle) ?>">
    <meta property="og:description" content="<?= e($seoDescription) ?>">
    <meta property="og:url" content="<?= e($seoCanonical) ?>">
    <meta property="og:site_name" content="<?= e($brand['name']) ?>">
    <?php if ($seoImage !== ''): ?><meta property="og:image" content="<?= e($seoImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($seoTitle) ?>">
    <meta name="twitter:description" content="<?= e($seoDescription) ?>">
    <?php if ($seoImage !== ''): ?><meta name="twitter:image" content="<?= e($seoImage) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/components.css')) ?>">
    <style>:root{<?= $themeStyle ?>--dk-primary-dark:color-mix(in srgb,var(--dk-primary) 82%,#000);--dk-primary-soft:color-mix(in srgb,var(--dk-primary) 10%,#fff);--dk-accent-soft:color-mix(in srgb,var(--dk-accent) 10%,#fff)}</style>
    <?php if (is_array($seoSchema)): ?><script type="application/ld+json"><?= json_encode($seoSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
</head>
<body class="customer-body">
<?= $content ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('/assets/js/app.js')) ?>"></script>
</body>
</html>
