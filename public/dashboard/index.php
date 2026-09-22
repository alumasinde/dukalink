<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Shops\ShopRepository;
use App\Support\Auth;
use function App\Support\e;

new App();

$db = Database::connection();
$merchant = Auth::requireMerchant($db);
$shopId = (int) $merchant['id'];

$shopRepo = new ShopRepository($db);
$shop = $shopRepo->find($shopId);
$products = (new ProductRepository($db))->allForShop($shopId);
$categories = (new CategoryRepository($db))->allForShop($shopId);

if (!$shop) {
    http_response_code(404);
    exit('Shop not found');
}

$activeProducts = count(array_filter($products, fn(array $p): bool => $p['status'] === 'active'));
$draftProducts = count(array_filter($products, fn(array $p): bool => $p['status'] === 'draft'));

$setupItems = [
    'Shop details' => !empty($shop['description']) && !empty($shop['phone']),
    'Store logo' => !empty($shop['logo_path']),
    'First product' => count($products) > 0,
    'Category' => count($categories) > 0,
    'M-Pesa' => !empty($shop['mpesa_credentials_configured']) || !empty($shop['allow_cash_on_delivery']),
];
$setupComplete = count(array_filter($setupItems)) ;
$setupTotal = count($setupItems);
$setupPercent = (int) round(($setupComplete / $setupTotal) * 100);

$recentProducts = array_slice($products, 0, 5);

ob_start();
?>
<div class="merchant-shell">
    <aside class="merchant-sidebar d-none d-lg-flex">
        <a href="/dashboard" class="brand-lockup mb-4">
            <span class="brand-mark">D</span>
            <span class="fw-bold">Dukame</span>
        </a>

        <div class="small text-uppercase text-secondary fw-bold mb-2">Shop</div>
        <nav class="merchant-nav">
            <a class="active" href="/dashboard"><span>▦</span> Overview</a>
            <a href="/products"><span>◫</span> Products</a>
            <a href="/categories"><span>◇</span> Categories</a>
        </nav>

        <div class="sidebar-bottom">
            <a href="<?= e(\App\Support\shop_url($shop['slug'])) ?>" target="_blank" class="store-preview-link">
                <span>↗</span> View my shop
            </a>
            <a href="/settings/shop" class="merchant-nav-link"><span>⚙</span> Settings</a>
            <a href="/logout" class="merchant-nav-link"><span>↪</span> Log out</a>
        </div>
    </aside>

    <main class="merchant-main dashboard-main">
        <div class="merchant-topbar dashboard-header">
            <div class="dashboard-shop-heading">
                <div class="dashboard-shop-avatar">
                    <?php if (!empty($shop['logo_path'])): ?>
                        <img src="<?= e($shop['logo_path']) ?>" alt="">
                    <?php else: ?>
                        <?= e(strtoupper(substr($shop['name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="small text-secondary">Your shop</div>
                    <h1 class="h3 fw-bold mb-0"><?= e($shop['name']) ?></h1>
                </div>
            </div>
            <div class="dashboard-actions">
                <a href="<?= e(\App\Support\shop_url($shop['slug'])) ?>" target="_blank" class="btn btn-outline-dark">View shop</a>
                <a href="/products/create" class="btn btn-primary">+ Add product</a>
            </div>
        </div>

        <section class="dashboard-welcome dashboard-welcome-modern">
            <div class="dashboard-welcome-copy">
                <span class="eyebrow">YOUR SHOP IS LIVE</span>
                <h2 class="h4 fw-bold mt-2 mb-1">Ready to sell?</h2>
                <p class="mb-3">Keep your catalogue fresh and share your store with customers.</p>
                <div class="dashboard-store-url">
                    <span>dukame.app/<?= e($shop['slug']) ?></span>
                    <button type="button" class="btn btn-sm btn-light" data-copy-text="<?= e('dukame.app/' . $shop['slug']) ?>">Copy link</button>
                </div>
            </div>
            <div class="dashboard-welcome-action">
                <a href="/settings/shop" class="btn btn-light">Customize shop</a>
            </div>
        </section>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <a href="/products" class="metric-card metric-card-link">
                    <div class="metric-label">Products</div>
                    <div class="metric-value"><?= count($products) ?></div>
                    <div class="metric-meta"><?= $activeProducts ?> live<?= $draftProducts ? ' · ' . $draftProducts . ' drafts' : '' ?></div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="/categories" class="metric-card metric-card-link">
                    <div class="metric-label">Categories</div>
                    <div class="metric-value"><?= count($categories) ?></div>
                    <div class="metric-meta">Organize your catalogue</div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="/settings/shop" class="metric-card metric-card-link">
                    <div class="metric-label">Store setup</div>
                    <div class="metric-value"><?= $setupComplete ?>/<?= $setupTotal ?></div>
                    <div class="metric-meta"><?= $setupPercent ?>% complete</div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-0 dashboard-lower-grid">
            <div class="col-lg-8">
                <section class="panel dashboard-products-panel h-100">
                    <div class="panel-heading">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Recent products</h2>
                            <p class="small text-secondary mb-0">Your latest catalogue items.</p>
                        </div>
                        <a href="/products" class="btn btn-sm btn-outline-dark">View all</a>
                    </div>

                    <?php if (!$recentProducts): ?>
                        <div class="empty-state dashboard-empty-state">
                            <div class="empty-icon">＋</div>
                            <h3 class="h5 fw-bold">Start your catalogue</h3>
                            <p class="text-secondary">Add your first product so customers have something to browse.</p>
                            <a href="/products/create" class="btn btn-primary">Add product</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 dashboard-products-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="d-none d-md-table-cell">Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="table-product-image <?= $product['image_path'] ? '' : 'placeholder' ?>">
                                                    <?php if ($product['image_path']): ?>
                                                        <img src="<?= e($product['image_path']) ?>" alt="">
                                                    <?php else: ?>
                                                        <span>◇</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-truncate dashboard-product-name"><?= e($product['name']) ?></div>
                                                    <div class="small text-secondary d-md-none"><?= e($product['category_name'] ?: 'Uncategorized') ?></div>
                                                    <div class="small text-secondary"><?= e($product['sku'] ?: 'No SKU') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?= e($product['category_name'] ?: 'Uncategorized') ?></td>
                                        <td class="text-nowrap"><?= e($shop['currency'] ?? 'KES') ?> <?= number_format((float) $product['price'], 2) ?></td>
                                        <td><span class="status-badge status-<?= e($product['status']) ?>"><?= e(ucfirst($product['status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="panel setup-panel h-100">
                    <div class="panel-heading">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Finish your setup</h2>
                            <p class="small text-secondary mb-0">A few details can make your shop more complete.</p>
                        </div>
                        <span class="setup-percent"><?= $setupPercent ?>%</span>
                    </div>
                    <div class="setup-body">
                        <div class="setup-progress"><span style="width: <?= $setupPercent ?>%"></span></div>
                        <div class="setup-list">
                            <?php foreach ($setupItems as $label => $complete): ?>
                                <a href="/settings/shop" class="setup-item">
                                    <span class="setup-check <?= $complete ? 'is-complete' : '' ?>"><?= $complete ? '✓' : '○' ?></span>
                                    <span><?= e($label) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($setupComplete < $setupTotal): ?>
                            <a href="/settings/shop" class="btn btn-outline-dark w-100 mt-2">Complete setup</a>
                        <?php else: ?>
                            <div class="setup-complete-note">Your shop is ready for the next step.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Dashboard';
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
