<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Categories\CategoryRepository;
use App\Support\Csrf;
use App\Support\Session;
use function App\Support\e;

new App();

$shopId = (int) Session::get('shop_id');
if (!$shopId) {
    header('Location: /register.php');
    exit;
}

$db = Database::connection();
$repo = new CategoryRepository($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                Session::flash('error', 'Category name is required.');
            } else {
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
                try {
                    $repo->create($shopId, $name, $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
                    Session::flash('success', 'Category created.');
                } catch (\Throwable $e) {
                    Session::flash('error', 'Could not create this category.');
                }
            }
        } elseif ($action === 'delete') {
            $repo->delete((int)$_POST['id'], $shopId);
            Session::flash('success', 'Category deleted.');
        }
    }
    header('Location: /categories/');
    exit;
}

$categories = $repo->allForShop($shopId);

ob_start();
?>
<div class="merchant-shell">
    <aside class="merchant-sidebar d-none d-lg-flex">
        <a href="/dashboard/" class="brand-lockup mb-4"><span class="brand-mark">D</span><span class="fw-bold">Dukame</span></a>
        <nav class="merchant-nav">
            <a href="/dashboard/"><span>▦</span> Overview</a>
            <a href="/products/"><span>◫</span> Products</a>
            <a class="active" href="/categories/"><span>◇</span> Categories</a>
        </nav>
    </aside>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Catalogue</div>
                <h1 class="h3 fw-bold mb-0">Categories</h1>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="panel p-4">
                    <h2 class="h5 fw-bold">Add a category</h2>
                    <p class="small text-secondary">Keep your storefront easy to browse.</p>

                    <?php require dirname(__DIR__, 2) . '/app/Views/components/alert.php'; ?>

                    <form method="post" class="mt-4">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="create">
                        <label class="form-label fw-semibold">Category name</label>
                        <input class="form-control form-control-lg mb-3" name="name" placeholder="Dresses" required>
                        <button class="btn btn-primary w-100">Add category</button>
                    </form>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Your categories</h2>
                            <p class="small text-secondary mb-0"><?= count($categories) ?> categories</p>
                        </div>
                    </div>

                    <?php if (!$categories): ?>
                        <div class="empty-state">
                            <div class="empty-icon">◇</div>
                            <h3 class="h6 fw-bold">No categories yet</h3>
                            <p class="small text-secondary mb-0">Create one to organize your products.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($categories as $category): ?>
                                <div class="list-group-item px-4 py-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold"><?= e($category['name']) ?></div>
                                        <div class="small text-secondary"><?= e($category['slug']) ?></div>
                                    </div>
                                    <form method="post" onsubmit="return confirm('Delete this category? Products will become uncategorized.');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>
<?php
$content = ob_get_clean();
$title = 'Categories';
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
