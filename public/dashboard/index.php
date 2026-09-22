<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Shops\ShopRepository;
use App\Support\Session;
use function App\Support\e;
use function App\Support\asset_url;

new App();

$shopId = (int) Session::get('shop_id');
if (!$shopId) {
    header('Location: /register.php');
    exit;
}

$db = Database::connection();
$shop = (new ShopRepository($db))->find($shopId);
$products = (new ProductRepository($db))->allForShop($shopId);
$categories = (new CategoryRepository($db))->allForShop($shopId);

$activeProducts = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$draftProducts = count(array_filter($products, fn($p) => $p['status'] === 'draft'));

ob_start();
?>
<div class="merchant-shell">
    <aside class="merchant-sidebar">
        <a href="/" class="brand-lockup mb-4">
            <span class="brand-mark">D</span>
            <span class="fw-bold">Dukame</span>
        </a>

        <div class="small text-uppercase text-secondary fw-bold mb-2">Shop</div>

        <nav class="merchant-nav">
            <a class="active" href="/dashboard/"><span>▦</span> Overview</a>
            <a href="/products/"><span>◫</span> Products</a>
            <a href="/categories/"><span>◇</span> Categories</a>
            <a href="#"><span>▣</span> Orders</a>
            <a href="#"><span>◉</span> Customers</a>
        </nav>

        <div class="sidebar-bottom">
            <a href="/shop/<?= e($shop['slug']) ?>" target="_blank" class="store-preview-link">
                <span>↗</span> View my shop
            </a>
            <a href="#" class="merchant-nav-link"><span>⚙</span> Settings</a>
        </div>
    </aside>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Your shop</div>
                <h1 class="h3 fw-bold mb-0"><?= e($shop['name']) ?></h1>
            </div>
            <a href="/products/create.php" class="btn btn-primary">+ Add product</a>
        </div>

        <section class="dashboard-welcome">
            <div>
                <span class="eyebrow">YOUR SHOP IS READY</span>
                <h2 class="h4 fw-bold mt-2 mb-1">Build your catalogue</h2>
                <p class="text-secondary mb-0">Add products and make your storefront useful to customers.</p>
            </div>
            <a href="/products/create.php" class="btn btn-light border">Add your first product</a>
        </section>

        <div class="row g-3 mt-1">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-label">Products</div>
                    <div class="metric-value"><?= count($products) ?></div>
                    <div class="metric-meta"><?= $activeProducts ?> live · <?= $draftProducts ?> drafts</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-label">Categories</div>
                    <div class="metric-value"><?= count($categories) ?></div>
                    <div class="metric-meta">Organize your catalogue</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-label">Orders</div>
                    <div class="metric-value">—</div>
                    <div class="metric-meta">Coming in the next phase</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <div class="metric-label">Customers</div>
                    <div class="metric-value">—</div>
                    <div class="metric-meta">Built from real orders</div>
                </div>
            </div>
        </div>

        <section class="panel mt-4">
            <div class="panel-heading">
                <div>
                    <h2 class="h5 fw-bold mb-1">Products</h2>
                    <p class="small text-secondary mb-0">Your latest catalogue items.</p>
                </div>
                <a href="/products/" class="btn btn-sm btn-outline-dark">View all</a>
            </div>

            <?php if (!$products): ?>
                <div class="empty-state">
                    <div class="empty-icon">＋</div>
                    <h3 class="h5 fw-bold">Your catalogue is empty</h3>
                    <p class="text-secondary">Add your first product and start building your storefront.</p>
                    <a href="/products/create.php" class="btn btn-primary">Add product</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($products, 0, 5) as $product): ?>
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
                                        <div>
                                            <div class="fw-semibold"><?= e($product['name']) ?></div>
                                            <div class="small text-secondary"><?= e($product['sku'] ?: 'No SKU') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($product['category_name'] ?: 'Uncategorized') ?></td>
                                <td>KSh <?= number_format((float)$product['price'], 2) ?></td>
                                <td><span class="status-badge status-<?= e($product['status']) ?>"><?= e(ucfirst($product['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Dashboard';
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
