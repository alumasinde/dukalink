<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\Auth;
use function App\Support\e;
use function App\Support\slugify;
use function App\Support\reserved_shop_slug;
use function App\Support\store_shop_logo;

new App();

$db = Database::connection();
$merchant = Auth::requireMerchant($db);
$shopId = (int)$merchant['id'];

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

        if ($action === 'publish' || $action === 'unpublish') {
            if ($action === 'publish') {
                $activeProducts = (new \App\Modules\Products\ProductRepository($db))->allForShop($shopId, 'active');
                $freshShop = $repo->find($shopId);
                $hasDetails = !empty($freshShop['description']) && !empty($freshShop['phone']);
                $hasWhatsApp = !empty($freshShop['whatsapp_number']);

                if (!$hasDetails || !$activeProducts || !$hasWhatsApp) {
                    $missing = [];
                    if (!$hasDetails) $missing[] = 'shop details';
                    if (!$activeProducts) $missing[] = 'at least one active product';
                    if (!$hasWhatsApp) $missing[] = 'WhatsApp number';
                    Session::flash('error', 'Before publishing, complete: ' . implode(', ', $missing) . '.');
                } else {
                    try {
                        $repo->publish($shopId);
                        Session::flash('success', 'Your shop is now live. Customers can visit your store.');
                    } catch (\Throwable $e) {
                        Session::flash('error', $e->getMessage());
                    }
                }
            } else {
                $repo->unpublish($shopId);
                Session::flash('success', 'Your shop has been unpublished.');
            }
        } elseif ($action === 'slug') {
            $requested = slugify((string)($_POST['slug'] ?? ''));

            if ($requested === '' || strlen($requested) < 3) {
                Session::flash('error', 'Shop links must be at least 3 characters.');
            } elseif (strlen($requested) > 80) {
                Session::flash('error', 'Shop links cannot exceed 80 characters.');
            } elseif (reserved_shop_slug($requested)) {
                Session::flash('error', 'That shop link is reserved. Please choose another one.');
            } elseif ($repo->slugExists($requested, $shopId)) {
                Session::flash('error', 'That shop link is already taken.');
            } else {
                $repo->updateSlug($shopId, $requested);
                Session::put('shop_slug', $requested);
                Session::flash('success', 'Your shop link has been updated.');
            }
        } elseif ($action === 'logo') {
            try {
                $logoPath = store_shop_logo($_FILES['logo'] ?? [], $shopId, $shop['logo_path'] ?? null);
                $repo->update($shopId, [
                    'name' => $shop['name'],
                    'business_type' => $shop['business_type'] ?? '',
                    'description' => $shop['description'] ?? '',
                    'phone' => $shop['phone'] ?? '',
                    'logo_path' => $logoPath,
                ]);
                $repo->updateLogo($shopId, $logoPath);
                Session::flash('success', 'Shop logo updated.');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        } elseif ($action === 'payments') {
            $currency = strtoupper(trim((string)($_POST['currency'] ?? 'KES')));
            $mpesaPhone = trim((string)($_POST['mpesa_phone'] ?? ''));
            $allowedCurrencies = ['KES', 'USD', 'UGX', 'TZS'];

            if (!in_array($currency, $allowedCurrencies, true)) {
                Session::flash('error', 'Please select a supported currency.');
            } else {
                $orderPrefix = strtoupper(trim((string)($_POST['order_number_prefix'] ?? 'DK')));
                $nextOrderNumber = (int)($_POST['next_order_number'] ?? 1001);
                $whatsappNumber = trim((string)($_POST['whatsapp_number'] ?? ''));
                if ($orderPrefix === '' || !preg_match('/^[A-Z0-9]{1,12}$/', $orderPrefix)) {
                    Session::flash('error', 'Order number prefix must contain 1–12 letters or numbers.');
                } elseif ($nextOrderNumber < 1) {
                    Session::flash('error', 'Next order number must be at least 1.');
                } else {
                    $repo->updateSettings($shopId, [
                        'currency' => $currency,
                        'order_number_prefix' => $orderPrefix,
                        'next_order_number' => $nextOrderNumber,
                        'whatsapp_number' => $whatsappNumber,
                        'mpesa_phone' => $mpesaPhone,
                        'mpesa_environment' => $_POST['mpesa_environment'] ?? 'sandbox',
                        'mpesa_shortcode' => trim((string)($_POST['mpesa_shortcode'] ?? '')),
                        'mpesa_consumer_key' => trim((string)($_POST['mpesa_consumer_key'] ?? '')),
                        'mpesa_consumer_secret' => trim((string)($_POST['mpesa_consumer_secret'] ?? '')),
                        'mpesa_passkey' => trim((string)($_POST['mpesa_passkey'] ?? '')),
                        'allow_cash_on_delivery' => isset($_POST['allow_cash_on_delivery']),
                    ]);
                    Session::flash('success', 'Payment and order settings updated.');
                }
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

$merchantShop = $shop ?? (new ShopRepository($db))->find($shopId);
$merchantSection = 'settings';
ob_start();
?>
<div class="merchant-shell">
    <?php require dirname(__DIR__, 2) . '/app/Views/components/merchant-sidebar.php'; ?>

    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Shop settings</div>
                <h1 class="h3 fw-bold mb-0">Customize your shop</h1>
            </div>
            <a href="<?= e(\App\Support\shop_url($shop['slug'])) ?>" target="_blank" class="btn btn-outline-dark">View storefront</a>
        </div>

        <?php require dirname(__DIR__, 2) . '/app/Views/components/alert.php'; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="panel p-4 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="shop-logo-preview">
                            <?php if (!empty($shop['logo_path'])): ?>
                                <img src="<?= e($shop['logo_path']) ?>" alt="Shop logo">
                            <?php else: ?>
                                <span><?= e(strtoupper(substr($shop['name'], 0, 1))) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 class="h5 fw-bold mb-1">Store logo</h2>
                            <p class="small text-secondary mb-0">Use a clear logo customers can recognize. JPG, PNG or WebP, up to 2MB.</p>
                        </div>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="mt-3">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="logo">
                        <input class="form-control" type="file" name="logo" accept="image/jpeg,image/png,image/webp" required>
                        <button class="btn btn-outline-dark mt-3">Upload logo</button>
                    </form>
                </section>

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
                        <div class="fw-bold mt-1">dukame.app/<?= e($shop['slug']) ?></div>
                    </div>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="slug">

                        <label class="form-label fw-semibold">Customize your link</label>
                        <div class="input-group">
                            <span class="input-group-text">dukame.app/</span>
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
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Store status</h2>
                            <p class="small text-secondary mb-0">
                                <?= $shop['status'] === 'active' ? 'Your store is visible to customers.' : 'Your store is not public yet.' ?>
                            </p>
                        </div>
                        <span class="status-badge status-<?= e($shop['status']) ?>"><?= e(ucfirst($shop['status'])) ?></span>
                    </div>
                    <?php if ($shop['status'] === 'active'): ?>
                        <form method="post" class="mt-3">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="unpublish">
                            <button class="btn btn-outline-danger w-100" type="submit">Unpublish store</button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="mt-3">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="publish">
                            <button class="btn btn-primary w-100" type="submit">Publish store</button>
                        </form>
                        <div class="form-text mt-2">You need shop details, at least one active product and a WhatsApp number. Logo and M-Pesa are optional.</div>
                    <?php endif; ?>
                </section>

                <section class="panel p-4 mt-4">
                    <h2 class="h5 fw-bold mb-1">Payments</h2>
                    <p class="small text-secondary">Choose which payment methods customers can use at checkout.</p>
                    <div class="payment-method-preview mt-3">
                        <div><span>M-Pesa</span><strong><?= !empty($shop['mpesa_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div>
                        <div><span>Cash on delivery</span><strong><?= !empty($shop['allow_cash_on_delivery']) ? 'Enabled' : 'Disabled' ?></strong></div>
                    </div>
                    <a class="btn btn-primary w-100 mt-3" href="/settings/payments">Manage payment methods</a>
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
$merchantLayout = true;
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
