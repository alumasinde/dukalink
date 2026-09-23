<?php

declare(strict_types=1);

$root = dirname(__DIR__, 5);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Products\ProductRepository;
use App\Modules\Subscriptions\EntitlementService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\Auth;
use function App\Support\e;
use function App\Support\store_product_image;

new App();

$db = Database::connection();
$merchant = Auth::requireMerchant($db);
$shopId = (int)$merchant['id'];

$db = Database::connection();
$categories = (new CategoryRepository($db))->allForShop($shopId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);

        if ($name === '' || $price < 0) {
            Session::flash('error', 'Product name and a valid price are required.');
        } else {
            try {
                (new EntitlementService($db))->assertCanCreate($shopId, 'products.max', 'products');
            } catch (\Throwable $limitError) {
                Session::flash('error', $limitError->getMessage());
                header('Location: /products');
                exit;
            }
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
            $slug = $slug ?: 'product-' . time();

            try {
                $imagePath = store_product_image($_FILES['image'] ?? [], $shopId);

                (new ProductRepository($db))->create($shopId, [
                    'category_id' => $_POST['category_id'] ?? null,
                    'name' => $name,
                    'slug' => $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
                    'description' => trim((string)($_POST['description'] ?? '')),
                    'options_json' => json_encode(array_values(array_filter(array_map('trim', explode(',', (string)($_POST['options'] ?? ''))))), JSON_UNESCAPED_UNICODE),
                    'sku' => trim((string)($_POST['sku'] ?? '')),
                    'price' => $price,
                    'compare_at_price' => $_POST['compare_at_price'] ?? null,
                    'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
                    'track_inventory' => isset($_POST['track_inventory']),
                    'image_path' => $imagePath,
                    'status' => $_POST['status'] ?? 'draft',
                    'featured' => isset($_POST['featured']),
                ]);

                Session::flash('success', 'Product created.');
                header('Location: /products');
                exit;
            } catch (\Throwable $e) {
                Session::flash('error', 'Could not create the product. Please check the details.');
            }
        }
    }
}

$merchantShop = $shop ?? (new ShopRepository($db))->find($shopId);
$merchantSection = 'products';
ob_start();
?>
<div class="merchant-shell">
    <?php require $root . '/app/Views/components/merchant-sidebar.php'; ?>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <a href="/products" class="small text-secondary"> Products</a>
                <h1 class="h3 fw-bold mt-1 mb-0">Add product</h1>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="row g-4">
            <?= Csrf::field() ?>
            <div class="col-lg-8">
                <section class="panel p-4">
                    <?php require $root . '/app/Views/components/alert.php'; ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Product name</label>
                        <input class="form-control form-control-lg" name="name" placeholder="e.g. Classic Summer Dress" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="5" placeholder="Tell customers about this product..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Options / sizes <span class="text-secondary">(optional)</span></label>
                        <input class="form-control" name="options" placeholder="e.g. Small, Medium, Large, XL">
                        <div class="form-text">Use this for sizes, colours, materials or other choices customers should select.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Selling price (KSh)</label>
                            <input class="form-control form-control-lg" type="number" step="0.01" min="0" name="price" placeholder="2500" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Original price</label>
                            <input class="form-control form-control-lg" type="number" step="0.01" min="0" name="compare_at_price" placeholder="3000">
                        </div>
                    </div>
                </section>

                <section class="panel p-4 mt-4">
                    <h2 class="h6 fw-bold mb-3">Inventory</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">SKU <span class="text-secondary">(optional)</span></label>
                            <input class="form-control" name="sku" placeholder="SKU-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock quantity</label>
                            <input class="form-control" type="number" min="0" name="stock_quantity" value="0">
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="track_inventory" id="trackInventory">
                        <label class="form-check-label" for="trackInventory">Track inventory for this product</label>
                    </div>
                    <div class="form-text mt-2">When enabled, a product with stock below 1 is shown as out of stock and customers cannot add it to their cart.</div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="panel p-4">
                    <h2 class="h6 fw-bold mb-3">Product image</h2>
                    <label class="form-label fw-semibold">Product image</label>
                    <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG or WebP · max 5MB.</div>
                </section>

                <section class="panel p-4 mt-4">
                    <h2 class="h6 fw-bold mb-3">Organization</h2>
                    <label class="form-label">Category</label>
                    <select class="form-select mb-3" name="category_id">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label">Status</label>
                    <select class="form-select mb-3" name="status">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                    </select>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="featured" id="featured">
                        <label class="form-check-label" for="featured">Feature on storefront</label>
                    </div>
                </section>

                <button class="btn btn-primary btn-lg w-100 mt-4">Save product</button>
            </div>
        </form>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Add product';
$merchantLayout = true;
require $root . '/app/Views/layouts/app.php';
