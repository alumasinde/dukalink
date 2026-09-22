<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Support\Session;
use function App\Support\e;

new App();

if (!Session::get('shop_id')) {
    header('Location: /register');
    exit;
}

$shopSlug = Session::get('shop_slug');

ob_start();
?>
<div class="onboarding-shell">
    <div class="onboarding-card text-center">
        <div class="success-icon mx-auto mb-4">✓</div>

        <div class="brand-mark mb-4">Dukame</div>

        <h1 class="h3 fw-bold">Your shop is started 🎉</h1>

        <p class="text-secondary">
            Your merchant account and shop foundation are ready.
        </p>

        <div class="shop-link-box my-4">
            <div class="small text-secondary mb-1">Your shop link</div>
            <strong>dukame.app/<?= e($shopSlug) ?></strong>
        </div>

        <a href="/dashboard" class="btn btn-primary btn-lg w-100">
            Continue to dashboard
        </a>

        <p class="small text-secondary mt-3 mb-0">
            Products, M-Pesa, orders and messaging will be added in the next phases.
        </p>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Shop setup complete';
require dirname(__DIR__) . '/app/Views/layouts/auth.php';
