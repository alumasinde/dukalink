<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Platform\PlatformRepository;
use App\Support\PlatformAuth;
use function App\Support\e;

new App();
$db = Database::connection();
$platformUser = PlatformAuth::requireAdmin($db);
$repo = new PlatformRepository($db);
$id = max(1, (int)($_GET['id'] ?? 0));
$shop = $repo->shopDetail($id);
if (!$shop) { http_response_code(404); require dirname(__DIR__) . '/404.php'; exit; }
$products = $repo->shopProducts($id, 8);
$orders = $repo->shopOrders($id, 8);
$base = PlatformAuth::basePath();

function shopDetailStatus(?string $status): string {
    return match ($status) {
        'active', 'paid' => 'status-active',
        'trial' => 'status-trial',
        'past_due' => 'status-past_due',
        'expired', 'cancelled', 'suspended', 'failed' => 'status-expired',
        default => 'status-draft',
    };
}
ob_start(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="platform-back" href="<?= e($base) ?>/shops">Shops</a>
        <div class="d-flex align-items-center gap-3 mt-2"><div class="platform-shop-avatar platform-shop-avatar-lg"><?= e(strtoupper(substr((string)$shop['name'],0,1))) ?></div><div><h2 class="platform-page-title mb-1"><?= e($shop['name']) ?></h2><div class="platform-muted">/<?= e($shop['slug']) ?></div></div></div>
        <p class="platform-muted mt-2 mb-0">Operational overview for this storefront. No merchant session is created.</p>
    </div>
    <?php if ($shop['status'] === 'active'): ?><a class="btn btn-primary" href="/<?= e($shop['slug']) ?>" target="_blank">View storefront</a><?php endif; ?>
</div>

<div class="row g-3 mb-4">
<?php foreach ([['Products',$shop['product_count'],(int)$shop['active_product_count'].' active'],['Categories',$shop['category_count'],'Catalogue structure'],['Orders',$shop['order_count'],'All-time orders'],['Customers',$shop['customer_count'],'Known customers']] as [$label,$value,$meta]): ?><div class="col-sm-6 col-xl-3"><div class="platform-stat"><div class="label"><?= e($label) ?></div><div class="value"><?= e(number_format((int)$value)) ?></div><div class="meta"><?= e($meta) ?></div></div></div><?php endforeach; ?>
</div>

<div class="row g-4">
<div class="col-xl-4">
<section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2>Shop overview</h2><p>Store and owner information.</p></div></div><div class="p-3">
<div class="detail-row"><span>Status</span><span class="status-badge <?= e(shopDetailStatus($shop['status'])) ?>"><?= e(ucfirst((string)$shop['status'])) ?></span></div>
<div class="detail-row"><span>Business type</span><strong><?= e($shop['business_type'] ?? '—') ?></strong></div>
<div class="detail-row"><span>Owner</span><strong><?= e(trim(($shop['first_name']??'').' '.($shop['last_name']??'')) ?: 'Merchant') ?></strong></div>
<div class="detail-row"><span>Phone</span><strong><?= e($shop['owner_phone'] ?? '—') ?></strong></div>
<div class="detail-row"><span>Currency</span><strong><?= e($shop['shop_currency'] ?? $shop['currency'] ?? 'KES') ?></strong></div>
<div class="detail-row"><span>Created</span><strong><?= e(date('d M Y',strtotime((string)$shop['created_at']))) ?></strong></div>
</div></section>
<section class="platform-panel"><div class="platform-panel-head"><div><h2>Subscription</h2><p>Plan and billing state.</p></div></div><div class="p-3">
<div class="detail-row"><span>Plan</span><strong><?= e($shop['plan_name'] ?? '—') ?></strong></div>
<div class="detail-row"><span>Status</span><span class="status-badge <?= e(shopDetailStatus($shop['subscription_status'])) ?>"><?= e(ucwords(str_replace('_',' ',(string)($shop['subscription_status'] ?? 'not set')))) ?></span></div>
<div class="detail-row"><span>Billing</span><strong><?= e(ucfirst((string)($shop['billing_interval'] ?? '—'))) ?></strong></div>
<div class="detail-row"><span>Paid order value</span><strong><?= e($shop['shop_currency'] ?? $shop['currency'] ?? 'KES') ?> <?= e(number_format((float)$shop['paid_order_value'],2)) ?></strong></div>
</div></section>
</div>
<div class="col-xl-8">
<section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2>Recent products</h2><p>Latest catalogue items.</p></div></div><div class="table-responsive"><table class="table platform-table align-middle mb-0"><thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead><tbody><?php if(!$products): ?><tr><td colspan="4"><div class="platform-empty">No products yet.</div></td></tr><?php endif; ?><?php foreach($products as $p): ?><tr><td><strong><?= e($p['name']) ?></strong><div class="platform-muted"><?= e($p['slug']) ?></div></td><td><?= e($shop['shop_currency'] ?? 'KES') ?> <?= e(number_format((float)$p['price'],2)) ?><?php if($p['compare_at_price']!==null): ?><div class="platform-muted">Was <?= e(number_format((float)$p['compare_at_price'],2)) ?></div><?php endif; ?></td><td><?= $p['track_inventory'] ? e((string)$p['stock_quantity']) : 'Not tracked' ?></td><td><span class="status-badge <?= e(shopDetailStatus($p['status'])) ?>"><?= e(ucfirst((string)$p['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="platform-panel"><div class="platform-panel-head"><div><h2>Recent orders</h2><p>Latest activity from this shop.</p></div></div><div class="table-responsive"><table class="table platform-table align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead><tbody><?php if(!$orders): ?><tr><td colspan="5"><div class="platform-empty">No orders yet.</div></td></tr><?php endif; ?><?php foreach($orders as $o): ?><tr><td><strong><?= e($o['order_number']) ?></strong><div class="platform-muted"><?= e(date('d M Y H:i',strtotime((string)$o['created_at']))) ?></div></td><td><?= e($o['customer_name']) ?></td><td><?= e($o['currency']) ?> <?= e(number_format((float)$o['total'],2)) ?></td><td><span class="status-badge <?= e(shopDetailStatus($o['payment_status'])) ?>"><?= e(ucfirst((string)$o['payment_status'])) ?></span></td><td><span class="status-badge <?= e(shopDetailStatus($o['status'])) ?>"><?= e(ucfirst((string)$o['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
</div></div>
<?php $content=ob_get_clean(); $title='Shop'; $platformSection='shops'; require dirname(__DIR__,2).'/app/Views/layouts/platform.php';
