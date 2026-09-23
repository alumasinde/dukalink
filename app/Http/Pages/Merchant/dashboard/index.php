<?php

declare(strict_types=1);

$root = dirname(__DIR__, 5);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Subscriptions\EntitlementService;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Shops\ShopRepository;
use App\Modules\Orders\OrderRepository;
use App\Modules\Customers\CustomerRepository;
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
$orderRepo = new OrderRepository($db);
$orderCounts = $orderRepo->countByStatus($shopId);
$customerCount = count((new CustomerRepository($db))->allForShop($shopId));
$orderCount = array_sum($orderCounts);
$subscriptionEntitlements = new EntitlementService($db);
$subscription = $subscriptionEntitlements->subscription($shopId);
$usageSummary = $subscriptionEntitlements->usageSummary($shopId);

if (!$shop) {
    http_response_code(404);
    exit('Shop not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\App\Support\Csrf::verify($_POST['_csrf'] ?? null)) {
        \App\Support\Session::flash('error', 'Your form session expired. Please try again.');
        header('Location: /dashboard');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'publish') {
        if (!$subscriptionEntitlements->isUsable($shopId)) {
            \App\Support\Session::flash('error', 'Your subscription is not active. Renew or choose an active plan before publishing your shop.');
            header('Location: /subscription');
            exit;
        }

        $hasActiveProduct = (new ProductRepository($db))->allForShop($shopId, 'active') !== [];
        $hasWhatsApp = trim((string)($shop['whatsapp_number'] ?? '')) !== '';
        $hasShopDetails = trim((string)($shop['name'] ?? '')) !== ''
            && trim((string)($shop['description'] ?? '')) !== ''
            && trim((string)($shop['phone'] ?? '')) !== '';

        $missing = [];
        if (!$hasShopDetails) $missing[] = 'shop details';
        if (!$hasActiveProduct) $missing[] = 'at least one active product';
        if (!$hasWhatsApp) $missing[] = 'WhatsApp number';

        if ($missing) {
            \App\Support\Session::flash('error', 'Before publishing, complete: ' . implode(', ', $missing) . '.');
        } else {
            try {
                $shopRepo->publish($shopId);
                \App\Support\Session::flash('success', 'Your shop is now live. Customers can visit your store.');
            } catch (\Throwable $e) {
                \App\Support\Session::flash('error', $e->getMessage());
            }
        }
    } elseif ($action === 'unpublish') {
        $shopRepo->unpublish($shopId);
        \App\Support\Session::flash('success', 'Your shop has been unpublished. Customers can no longer place new orders.');
    }

    header('Location: /dashboard');
    exit;
}

$activeProducts = count(array_filter($products, fn(array $p): bool => $p['status'] === 'active'));
$draftProducts = count(array_filter($products, fn(array $p): bool => $p['status'] === 'draft'));

$setupItems = [
    'Shop details' => !empty($shop['description']) && !empty($shop['phone']),
    'Store logo' => !empty($shop['logo_path']),
    'First product' => $activeProducts > 0,
    'Category' => count($categories) > 0,
    'WhatsApp' => !empty($shop['whatsapp_number']),
    'M-Pesa' => !empty($shop['mpesa_credentials_configured']) || !empty($shop['allow_cash_on_delivery']),
];
$setupComplete = count(array_filter($setupItems)) ;
$setupTotal = count($setupItems);
$setupPercent = (int) round(($setupComplete / $setupTotal) * 100);

$recentProducts = array_slice($products, 0, 5);

$merchantShop = $shop ?? (new ShopRepository($db))->find($shopId);
$merchantShop = $shop;
$merchantSection = 'overview';
ob_start();
?>
<div class="merchant-shell">
    <?php require $root . '/app/Views/components/merchant-sidebar.php'; ?>

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
                <span class="eyebrow"><?= $shop['status'] === 'active' ? 'YOUR SHOP IS LIVE' : 'YOUR SHOP IS IN DRAFT' ?></span>
                <h2 class="h4 fw-bold mt-2 mb-1"><?= $shop['status'] === 'active' ? 'Ready to sell?' : 'Your shop is ready to go live' ?></h2>
                <p class="mb-3"><?= $shop['status'] === 'active' ? 'Keep your catalogue fresh and share your store with customers.' : 'Your customers cannot see this shop yet. Publish it when you are ready.' ?></p>
                <div class="dashboard-store-url">
                    <span>dukame.app/<?= e($shop['slug']) ?></span>
                    <button type="button" class="btn btn-sm btn-light" data-copy-text="<?= e('dukame.app/' . $shop['slug']) ?>">Copy link</button>
                </div>
            </div>
            <div class="dashboard-welcome-action">
                <?php if ($shop['status'] === 'active'): ?>
                    <a href="<?= e(\App\Support\shop_url($shop['slug'])) ?>" target="_blank" class="btn btn-light">View shop</a>
                    <form method="post" class="d-inline mt-2 mt-md-0">
                        <?= \App\Support\Csrf::field() ?>
                        <input type="hidden" name="action" value="unpublish">
                        <button class="btn btn-outline-light" type="submit">Unpublish</button>
                    </form>
                <?php else: ?>
                    <form method="post" class="d-inline">
                        <?= \App\Support\Csrf::field() ?>
                        <input type="hidden" name="action" value="publish">
                        <button class="btn btn-light fw-semibold" type="submit">Publish store</button>
                    </form>
                    <a href="/settings/shop" class="btn btn-outline-light mt-2 mt-md-0">Customize shop</a>
                <?php endif; ?>
            </div>
        </section>



        <?php
        $mpesaAvailable = !empty($shop['mpesa_enabled']) && !empty($shop['mpesa_credentials_configured']) && !empty($shop['mpesa_phone']) && (($shop['currency'] ?? 'KES') === 'KES');
        $codAvailable = !empty($shop['allow_cash_on_delivery']);
        ?>
        <?php if (!$mpesaAvailable && !$codAvailable): ?>
            <section class="dashboard-payment-alert">
                <div>
                    <div class="small fw-bold text-uppercase">Checkout setup</div>
                    <h2 class="h6 fw-bold mt-1 mb-1">Your store has no payment method enabled</h2>
                    <p class="small mb-0">Customers can browse your products, but they cannot complete checkout until you enable M-Pesa or cash on delivery.</p>
                </div>
                <a href="/settings/payments" class="btn btn-primary">Enable payments</a>
            </section>
        <?php endif; ?>

        <?php if ($subscription): ?>
            <section class="panel subscription-dashboard-card mt-3">
                <div class="p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                            <div class="small text-uppercase fw-bold text-secondary">Subscription</div>
                            <h2 class="h5 fw-bold mt-1 mb-1"><?= e($subscription['plan_name']) ?> plan <span class="status-badge status-<?= e($subscription['status']) ?> ms-1"><?= e(ucwords(str_replace('_',' ',$subscription['status']))) ?></span></h2>
                            <p class="small text-secondary mb-0"><?php if ($subscription['status']==='trial'): ?>Trial ends <?= e(date('M j, Y', strtotime($subscription['trial_ends_at']))) ?><?php elseif (!empty($subscription['current_period_end'])): ?>Current period ends <?= e(date('M j, Y', strtotime($subscription['current_period_end']))) ?><?php else: ?>Manage your plan and usage here.<?php endif; ?></p>
                        </div>
                        <a href="/subscription" class="btn btn-outline-dark align-self-start">Manage subscription</a>
                    </div>
                    <div class="row g-3 mt-1">
                        <?php foreach ($usageSummary as $item): ?>
                            <div class="col-md-4">
                                <div class="subscription-usage-mini">
                                    <div class="d-flex justify-content-between small fw-semibold"><span><?= e($item['label']) ?></span><span><?= $item['unlimited'] ? 'Unlimited' : e((string)$item['usage'].' / '.(string)$item['limit']) ?></span></div>
                                    <?php if (!$item['unlimited']): ?><div class="progress mt-2"><div class="progress-bar" style="width: <?= $item['limit'] > 0 ? min(100, round(($item['usage']/$item['limit'])*100)) : 100 ?>%"></div></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="row g-3 mt-1">
            <div class="col-6 col-xl-3">
                <a href="/products" class="metric-card metric-card-link">
                    <div class="metric-label">Products</div>
                    <div class="metric-value"><?= count($products) ?></div>
                    <div class="metric-meta"><?= $activeProducts ?> live<?= $draftProducts ? ' · ' . $draftProducts . ' drafts' : '' ?></div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a href="/categories" class="metric-card metric-card-link">
                    <div class="metric-label">Categories</div>
                    <div class="metric-value"><?= count($categories) ?></div>
                    <div class="metric-meta">Catalogue groups</div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a href="/orders" class="metric-card metric-card-link">
                    <div class="metric-label">Orders</div>
                    <div class="metric-value"><?= $orderCount ?></div>
                    <div class="metric-meta"><?= $orderCounts['pending'] ?> awaiting confirmation</div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a href="/customers" class="metric-card metric-card-link">
                    <div class="metric-label">Customers</div>
                    <div class="metric-value"><?= $customerCount ?></div>
                    <div class="metric-meta">From your orders</div>
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
                        <?php
                        $canPublish = !empty($shop['description'])
                            && !empty($shop['phone'])
                            && $activeProducts > 0
                            && !empty($shop['whatsapp_number'])
                            && $shop['status'] !== 'suspended';
                        ?>
                        <?php if ($shop['status'] === 'active'): ?>
                            <div class="setup-complete-note">Your shop is live. Customers can visit and place orders.</div>
                        <?php elseif ($canPublish): ?>
                            <div class="setup-complete-note">Everything needed to publish is ready.</div>
                            <form method="post" class="mt-2">
                                <?= \App\Support\Csrf::field() ?>
                                <input type="hidden" name="action" value="publish">
                                <button class="btn btn-primary w-100" type="submit">Publish store</button>
                            </form>
                        <?php else: ?>
                            <a href="/settings/shop" class="btn btn-outline-dark w-100 mt-2">Complete setup</a>
                            <div class="small text-secondary mt-2">Logo, category and M-Pesa can be added later.</div>
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
$merchantLayout = true;
require $root . '/app/Views/layouts/app.php';
