<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Support\Csrf;
use App\Support\Session;
use function App\Support\e;
use function App\Support\slugify;

new App();

$shopId = (int) Session::get('shop_id');
if (!$shopId) {
    header('Location: /register');
    exit;
}

$db = Database::connection();
$repo = new ShopRepository($db);
$shop = $repo->find($shopId);

if (!$shop) {
    http_response_code(404);
    exit('Shop not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? 'details';

        if ($action === 'slug') {
            $requested = slugify((string)($_POST['slug'] ?? ''));

            if ($requested === '' || strlen($requested) < 3) {
                Session::flash('error', 'Shop links must be at least 3 characters.');
            } elseif (strlen($requested) > 80) {
                Session::flash('error', 'Shop links cannot exceed 80 characters.');
            } elseif ($repo->slugExists($requested, $shopId)) {
                Session::flash('error', 'That shop link is already taken.');
            } else {
                $repo->updateSlug($shopId, $requested);
                Session::put('shop_slug', $requested);
                Session::flash('success', 'Your shop link has been updated.');
            }
        } else {
            $name = trim((string)($_POST['name'] ?? ''));
            $type = trim((string)($_POST['business_type'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));

            if ($name === '') {
                Session::flash('error', 'Shop name is required.');
            } else {
                $repo->update($shopId, [
                    'name' => $name,
                    'business_type' => $type,
                    'description' => $description,
                    'phone' => $phone,
                ]);
                Session::flash('success', 'Shop details updated.');
            }
        }
    }

    header('Location: /settings/shop');
    exit;
}

$shop = $repo->find($shopId);

ob_start();
?>
<div class="merchant-shell">
    <aside class="merchant-sidebar d-none d-lg-flex">
        <a href="/dashboard" class="brand-lockup mb-4"><span class="brand-mark">D</span><span class="fw-bold">Dukame</span></a>
        <nav class="merchant-nav">
            <a href="/dashboard"><span>▦</span> Overview</a>
            <a href="/products"><span>◫</span> Products</a>
            <a href="/categories"><span>◇</span> Categories</a>
            <a class="active" href="/settings/shop"><span>⚙</span> Settings</a>
        </nav>
        <div class="sidebar-bottom">
            <a href="/shop/<?= e($shop['slug']) ?>" target="_blank" class="store-preview-link"><span>↗</span> View my shop</a>
        </div>
    </aside>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Shop settings</div>
                <h1 class="h3 fw-bold mb-0">Customize your shop</h1>
            </div>
            <a href="/shop/<?= e($shop['slug']) ?>" target="_blank" class="btn btn-outline-dark">View storefront ↗</a>
        </div>

        <?php require dirname(__DIR__, 2) . '/app/Views/components/alert.php'; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="panel p-4">
                    <h2 class="h5 fw-bold">Shop details</h2>
                    <p class="small text-secondary">These details appear on your customer storefront.</p>

                    <form method="post" class="mt-4">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="details">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Shop name</label>
                            <input class="form-control form-control-lg" name="name" value="<?= e($shop['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Business category</label>
                            <input class="form-control" name="business_type" value="<?= e($shop['business_type'] ?? '') ?>" placeholder="Fashion & Clothing">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" name="description" rows="4" maxlength="500" placeholder="Tell customers what your shop sells..."><?= e($shop['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Shop phone</label>
                            <input class="form-control" name="phone" value="<?= e($shop['phone'] ?? '') ?>" placeholder="0712 345 678">
                        </div>

                        <button class="btn btn-primary">Save shop details</button>
                    </form>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="panel p-4">
                    <h2 class="h5 fw-bold">Shop link</h2>
                    <p class="small text-secondary">This is the public address customers use to find you.</p>

                    <div class="shop-link-preview my-4">
                        <div class="small text-secondary">Current link</div>
                        <div class="fw-bold mt-1">/shop/<?= e($shop['slug']) ?></div>
                    </div>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="slug">

                        <label class="form-label fw-semibold">Customize your link</label>
                        <div class="input-group">
                            <span class="input-group-text">/shop/</span>
                            <input
                                class="form-control"
                                name="slug"
                                value="<?= e($shop['slug']) ?>"
                                minlength="3"
                                maxlength="80"
                                pattern="[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*"
                                required
                            >
                        </div>
                        <div class="form-text mb-3">
                            Letters, numbers and hyphens only. Your link must be unique.
                        </div>

                        <button class="btn btn-outline-dark w-100">Change shop link</button>
                    </form>
                </section>

                <section class="panel p-4 mt-4">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h2 class="h6 fw-bold mb-1">Store status</h2>
                            <p class="small text-secondary mb-0">Your storefront is currently available.</p>
                        </div>
                        <span class="status-badge status-active">Live</span>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Shop settings';
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
