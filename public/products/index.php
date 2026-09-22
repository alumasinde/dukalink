<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Modules\Products\ProductRepository;
use App\Support\Session;
use App\Support\Auth;
use App\Support\Csrf;
use function App\Support\e;

new App();

$db = Database::connection();
$merchant = Auth::requireMerchant($db);
$shopId = (int)$merchant['id'];

$productRepo = new ProductRepository($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $productRepo->delete($id, $shopId);
            Session::flash('success', 'Product deleted.');
        }
    }
    header('Location: /products');
    exit;
}


$products = $productRepo->allForShop($shopId);

$merchantShop = $shop ?? (new ShopRepository($db))->find($shopId);
$merchantSection = 'products';
ob_start();
?>
<div class="merchant-shell">
    <?php require dirname(__DIR__, 2) . '/app/Views/components/merchant-sidebar.php'; ?>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Catalogue</div>
                <h1 class="h3 fw-bold mb-0">Products</h1>
            </div>
            <a href="/products/create" class="btn btn-primary">+ Add product</a>
        </div>

        <div class="panel">
            <?php require dirname(__DIR__, 2) . '/app/Views/components/alert.php'; ?>
            <div class="panel-heading flex-wrap">
                <div>
                    <h2 class="h5 fw-bold mb-1">All products</h2>
                    <p class="small text-secondary mb-0"><?= count($products) ?> products in your shop</p>
                </div>
                <div class="catalogue-search">
                    <input class="form-control" type="search" placeholder="Search products..." data-product-search>
                </div>
            </div>

            <?php if (!$products): ?>
                <div class="empty-state">
                    <div class="empty-icon">◇</div>
                    <h3 class="h5 fw-bold">No products yet</h3>
                    <p class="text-secondary">Add a product with a name, price and optional image.</p>
                    <a href="/products/create" class="btn btn-primary">Add product</a>
                </div>
            <?php else: ?>
                <div class="row g-3 p-3" data-product-grid>
                    <?php foreach ($products as $product): ?>
                        <div class="col-sm-6 col-xl-4 product-list-item" data-product-name="<?= e(strtolower($product['name'])) ?>">
                            <article class="catalogue-card h-100">
                                <div class="catalogue-image <?= $product['image_path'] ? '' : 'placeholder' ?>">
                                    <?php if ($product['image_path']): ?>
                                        <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
                                    <?php else: ?>
                                        <span>◇</span>
                                    <?php endif; ?>
                                    <span class="status-badge status-<?= e($product['status']) ?>"><?= e(ucfirst($product['status'])) ?></span>
                                </div>
                                <div class="p-3">
                                    <div class="small text-secondary"><?= e($product['category_name'] ?: 'Uncategorized') ?></div>
                                    <h3 class="h6 fw-bold mt-1 mb-1"><?= e($product['name']) ?></h3>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php if (!empty($product['track_inventory'])): ?>
                                            <?php if ((int)$product['stock_quantity'] < 1): ?>
                                                <span class="badge text-bg-light border text-danger">Out of stock</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-light border text-secondary"><?= (int)$product['stock_quantity'] ?> in stock</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge text-bg-light border text-secondary">Inventory not tracked</span>
                                        <?php endif; ?>
                                        <?php if ((float)($product['compare_at_price'] ?? 0) > (float)$product['price']): ?>
                                            <span class="badge text-bg-light border text-warning-emphasis">Offer</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mt-3">
                                        <div>
                                            <div class="fw-bold">KSh <?= number_format((float)$product['price'], 2) ?></div>
                                            <?php if ($product['compare_at_price']): ?>
                                                <div class="small text-secondary text-decoration-line-through">KSh <?= number_format((float)$product['compare_at_price'], 2) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2">
<a href="/products/edit?id=<?= (int)$product['id'] ?>" class="btn btn-sm btn-outline-dark">Edit</a>
<form method="post" onsubmit="return confirm('Delete this product?');">
<?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
<button class="btn btn-sm btn-outline-danger">Delete</button>
</form>
</div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Products';
$merchantLayout = true;
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
