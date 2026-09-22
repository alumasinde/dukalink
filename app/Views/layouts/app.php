<?php
use function App\Support\e;
use function App\Support\asset_url;
$title=$title??'Dukame'; $content=$content??''; $brand=require dirname(__DIR__,3).'/config/branding.php';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> · <?=e($brand['name'])?></title><meta name="description" content="<?=e($brand['tagline'])?>"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/variables.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/components.css'))?>"></head><body>
<?php require __DIR__.'/../components/navbar.php'; ?>
<main><?=$content?></main><footer class="site-footer"><div class="container py-4 d-flex flex-column flex-md-row justify-content-between gap-2"><span class="small text-secondary">© <?=date('Y')?> <?=e($brand['name'])?></span><span class="small text-secondary"><?=e($brand['tagline'])?></span></div></footer><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?=e(asset_url('/assets/js/app.js'))?>"></script></body></html>
