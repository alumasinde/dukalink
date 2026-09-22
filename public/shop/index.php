<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Products\ProductRepository;
use App\Modules\Shops\ShopRepository;
use function App\Support\e;

new App();

$slug = trim((string)($_GET['slug'] ?? ''));
$db = Database::connection();

$stmt = $db->prepare('SELECT id FROM shops WHERE slug = :slug AND status = "active" LIMIT 1');
$stmt->execute(['slug' => $slug]);
$shopId = (int)($stmt->fetchColumn() ?: 0);

if (!$shopId) {
    http_response_code(404);
    echo 'Shop not found';
    exit;
}

$shop = (new ShopRepository($db))->find($shopId);
$products = (new ProductRepository($db))->allForShop($shopId, 'active');
$categories = (new CategoryRepository($db))->allForShop($shopId, true);

$selectedCategory = (int)($_GET['category'] ?? 0);
if ($selectedCategory) {
    $products = array_values(array_filter($products, fn($p) => (int)$p['category_id'] === $selectedCategory));
}

ob_start();
?>
<div class="storefront">
    <header class="storefront-header">
        <div class="container py-4">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div>
                    <div class="store-name"><?= e($shop['name']) ?></div>
                    <?php if ($shop['description']): ?>
                        <div class="small text-secondary mt-1"><?= e($shop['description']) ?></div>
                    <?php else: ?>
                        <div class="small text-secondary mt-1"><?= e($shop['business_type'] ?: 'Online shop') ?></div>
                    <?php endif; ?>
                </div>

                <button class="store-cart-button" type="button" data-cart-button>
                    🛒 <span data-cart-count>0</span>
                </button>
            </div>

            <div class="store-search mt-4">
                <span>⌕</span>
                <input type="search" placeholder="Search products..." data-store-search>
            </div>

            <?php if ($categories): ?>
                <div class="category-scroller mt-3">
                    <a href="?slug=<?= e($slug) ?>" class="category-chip <?= !$selectedCategory ? 'active' : '' ?>">All</a>
                    <?php foreach ($categories as $category): ?>
                        <a href="?slug=<?= e($slug) ?>&category=<?= (int)$category['id'] ?>"
                           class="category-chip <?= $selectedCategory === (int)$category['id'] ? 'active' : '' ?>">
                            <?= e($category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container py-4 pb-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <div class="eyebrow">SHOP COLLECTION</div>
                <h1 class="h4 fw-bold mt-1 mb-0"><?= e($selectedCategory ? 'Products' : 'All products') ?></h1>
            </div>
            <span class="small text-secondary"><?= count($products) ?> items</span>
        </div>

        <?php if (!$products): ?>
            <div class="store-empty">
                <div class="empty-icon">◇</div>
                <h2 class="h5 fw-bold">Nothing here yet</h2>
                <p class="text-secondary">This shop hasn't added products to this collection.</p>
            </div>
        <?php else: ?>
            <div class="row g-3 g-md-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-6 col-md-4 col-lg-3 store-product" data-name="<?= e(strtolower($product['name'])) ?>">
                        <article class="store-product-card">
                            <div class="store-product-image <?= $product['image_path'] ? '' : 'placeholder' ?>">
                                <?php if ($product['image_path']): ?>
                                    <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
                                <?php else: ?>
                                    <span>◇</span>
                                <?php endif; ?>
                                <?php if ($product['featured']): ?>
                                    <span class="featured-pill">Featured</span>
                                <?php endif; ?>
                            </div>

                            <div class="pt-3">
                                <div class="small text-secondary"><?= e($product['category_name'] ?: 'Shop') ?></div>
                                <h2 class="h6 fw-bold mt-1 mb-1 product-title"><?= e($product['name']) ?></h2>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="fw-bold">KSh <?= number_format((float)$product['price'], 2) ?></span>
                                        <?php if ($product['compare_at_price']): ?>
                                            <span class="small text-secondary text-decoration-line-through ms-1">
                                                KSh <?= number_format((float)$product['compare_at_price'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <button
                                        class="add-cart-button"
                                        type="button"
                                        data-add-cart
                                        data-product-id="<?= (int)$product['id'] ?>"
                                        data-product-name="<?= e($product['name']) ?>"
                                        data-product-price="<?= e((string)$product['price']) ?>"
                                    >+</button>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div class="floating-cart" data-floating-cart hidden>
        <div>
            <div class="small text-white-50">Your cart</div>
            <strong><span data-cart-count>0</span> items</strong>
        </div>
        <button type="button" class="btn btn-light btn-sm fw-bold">View cart →</button>
    </div>
</div>

<script>
window.DUKAME_STORE = {
    slug: <?= json_encode($slug) ?>
};
</script>
<?php
$content = ob_get_clean();
$title = $shop['name'];
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
