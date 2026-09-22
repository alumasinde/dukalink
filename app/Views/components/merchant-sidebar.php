<?php
use function App\Support\e;
use function App\Support\shop_url;
$merchantShop = $merchantShop ?? null;
$merchantSection = $merchantSection ?? 'overview';
?>
<aside class="merchant-sidebar">
    <a href="/dashboard" class="brand-lockup merchant-brand">
        <span class="brand-mark">D</span><span class="fw-bold">Dukame</span>
    </a>

    <?php if ($merchantShop): ?>
        <div class="merchant-sidebar-shop">
            <div class="merchant-sidebar-avatar">
                <?php if (!empty($merchantShop['logo_path'])): ?>
                    <img src="<?= e($merchantShop['logo_path']) ?>" alt="">
                <?php else: ?>
                    <?= e(strtoupper(substr((string)$merchantShop['name'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="min-w-0">
                <div class="small fw-semibold text-truncate"><?= e($merchantShop['name']) ?></div>
                <div class="sidebar-shop-link text-truncate">/<?= e($merchantShop['slug']) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="small text-uppercase text-secondary fw-bold mb-2">Manage</div>
    <nav class="merchant-nav">
        <a class="<?= $merchantSection === 'overview' ? 'active' : '' ?>" href="/dashboard"><span>▦</span> Overview</a>
        <a class="<?= $merchantSection === 'products' ? 'active' : '' ?>" href="/products"><span>◫</span> Products</a>
        <a class="<?= $merchantSection === 'categories' ? 'active' : '' ?>" href="/categories"><span>◇</span> Categories</a>
        <a class="<?= $merchantSection === 'orders' ? 'active' : '' ?>" href="/orders"><span>▤</span> Orders</a>
        <a class="<?= $merchantSection === 'customers' ? 'active' : '' ?>" href="/customers"><span>♙</span> Customers</a>
    </nav>

    <div class="small text-uppercase text-secondary fw-bold mb-2 mt-4">Store</div>
    <nav class="merchant-nav">
        <?php if ($merchantShop): ?>
            <a href="<?= e(shop_url((string)$merchantShop['slug'])) ?>" target="_blank"><span> </span> View my shop</a>
        <?php endif; ?>
        <a class="<?= $merchantSection === 'settings' ? 'active' : '' ?>" href="/settings/shop"><span>⚙</span> Shop settings</a>
        <a class="<?= $merchantSection === 'payments' ? 'active' : '' ?>" href="/settings/payments"><span>◉</span> Payments</a>
        <a class="<?= $merchantSection === 'delivery' ? 'active' : '' ?>" href="/settings/delivery"><span>⌂</span> Delivery & Pickup</a>
        <a class="<?= $merchantSection === 'notifications' ? 'active' : '' ?>" href="/settings/notifications"><span>✉</span> SMS templates</a>
        <a class="<?= $merchantSection === 'subscription' ? 'active' : '' ?>" href="/subscription"><span>◇</span> Subscription</a>
    </nav>

    <div class="sidebar-bottom">
        <a href="/logout" class="merchant-nav-link"><span>↪</span> Log out</a>
    </div>
</aside>

<div class="merchant-mobile-bar">
    <a href="/dashboard" class="brand-lockup">
        <span class="brand-mark">D</span><span class="fw-bold">Dukame</span>
    </a>
    <button class="btn btn-light border" type="button" data-bs-toggle="offcanvas" data-bs-target="#merchantMobileNav" aria-controls="merchantMobileNav">Menu</button>
</div>

<div class="offcanvas offcanvas-start merchant-mobile-nav" tabindex="-1" id="merchantMobileNav" aria-labelledby="merchantMobileNavLabel">
    <div class="offcanvas-header">
        <div class="brand-lockup" id="merchantMobileNavLabel"><span class="brand-mark">D</span><span class="fw-bold">Dukame</span></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <?php $mobileNav = ['overview' => ['/dashboard','▦','Overview'], 'products' => ['/products','◫','Products'], 'categories' => ['/categories','◇','Categories'], 'orders' => ['/orders','▤','Orders'], 'customers' => ['/customers','♙','Customers'], 'settings' => ['/settings/shop','⚙','Shop settings'], 'payments' => ['/settings/payments','◉','Payments'], 'delivery' => ['/settings/delivery','⌂','Delivery & Pickup'], 'notifications' => ['/settings/notifications','✉','SMS templates'], 'subscription' => ['/subscription','◇','Subscription']]; ?>
        <nav class="merchant-nav">
            <?php foreach ($mobileNav as $key => [$url,$icon,$label]): ?>
                <a class="<?= $merchantSection === $key ? 'active' : '' ?>" href="<?= e($url) ?>"><span><?= e($icon) ?></span> <?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($merchantShop): ?><a href="<?= e(shop_url((string)$merchantShop['slug'])) ?>" target="_blank"><span> </span> View my shop</a><?php endif; ?>
            <a href="/logout"><span>↪</span> Log out</a>
        </nav>
    </div>
</div>
