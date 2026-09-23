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
$merchant = $repo->merchantDetail($id);
if (!$merchant) { http_response_code(404); require dirname(__DIR__) . '/404.php'; exit; }
$orders = $repo->merchantOrders($id, 8);
$paymentSummary = $repo->merchantPaymentSummary($id);
$base = PlatformAuth::basePath();

function detailStatus(?string $status): string {
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
        <a class="platform-back" href="<?= e($base) ?>/merchants">Merchants</a>
        <h2 class="platform-page-title"><?= e(trim(($merchant['first_name'] ?? '') . ' ' . ($merchant['last_name'] ?? '')) ?: 'Merchant') ?></h2>
        <p class="platform-muted mb-0">Merchant account and shop activity. This is an administrative view, not impersonation.</p>
    </div>
    <?php if (!empty($merchant['shop_id'])): ?><a class="btn btn-primary" href="<?= e($base) ?>/shops/view/<?= (int)$merchant['shop_id'] ?>">View shop</a><?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="platform-stat"><div class="label">Account</div><div class="value fs-4"><?= e(ucfirst((string)$merchant['status'])) ?></div><div class="meta">Joined <?= e(date('d M Y', strtotime((string)$merchant['created_at']))) ?></div></div></div>
    <div class="col-md-3"><div class="platform-stat"><div class="label">Shop</div><div class="value fs-4"><?= e($merchant['shop_name'] ?? 'None') ?></div><div class="meta"><?= !empty($merchant['slug']) ? '/'.e($merchant['slug']) : 'No shop created' ?></div></div></div>
    <div class="col-md-3"><div class="platform-stat"><div class="label">Subscription</div><div class="value fs-4"><?= e($merchant['plan_name'] ?? 'None') ?></div><div class="meta"><?= e(ucwords(str_replace('_',' ',(string)($merchant['subscription_status'] ?? 'not set')))) ?></div></div></div>
    <div class="col-md-3"><div class="platform-stat"><div class="label">Paid subscription value</div><div class="value fs-4">KSh <?= e(number_format((float)$paymentSummary['paid_amount'], 0)) ?></div><div class="meta"><?= (int)$paymentSummary['paid_count'] ?> successful payment<?= (int)$paymentSummary['paid_count'] === 1 ? '' : 's' ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2>Account</h2><p>Platform-visible merchant information.</p></div></div><div class="p-3">
            <div class="detail-row"><span>Name</span><strong><?= e(trim(($merchant['first_name'] ?? '').' '.($merchant['last_name'] ?? '')) ?: 'Merchant') ?></strong></div>
            <div class="detail-row"><span>Phone</span><strong><?= e($merchant['phone'] ?? '—') ?></strong></div>
            <div class="detail-row"><span>Email</span><strong><?= e($merchant['email'] ?? '—') ?></strong></div>
            <div class="detail-row"><span>Status</span><span class="status-badge <?= e(detailStatus($merchant['status'])) ?>"><?= e(ucfirst((string)$merchant['status'])) ?></span></div>
            <div class="detail-row"><span>Role</span><strong><?= e($merchant['role'] ?? 'merchant') ?></strong></div>
        </div></section>
        <section class="platform-panel"><div class="platform-panel-head"><div><h2>Subscription</h2><p>Current entitlement source.</p></div></div><div class="p-3">
            <div class="detail-row"><span>Plan</span><strong><?= e($merchant['plan_name'] ?? '—') ?></strong></div>
            <div class="detail-row"><span>Status</span><span class="status-badge <?= e(detailStatus($merchant['subscription_status'])) ?>"><?= e(ucwords(str_replace('_',' ',(string)($merchant['subscription_status'] ?? 'not set')))) ?></span></div>
            <div class="detail-row"><span>Billing</span><strong><?= e(ucfirst((string)($merchant['billing_interval'] ?? '—'))) ?></strong></div>
            <div class="detail-row"><span>Current period</span><strong><?= !empty($merchant['current_period_start']) ? e(date('d M Y',strtotime($merchant['current_period_start'])).' – '.date('d M Y',strtotime((string)$merchant['current_period_end']))) : '—' ?></strong></div>
        </div></section>
    </div>
    <div class="col-xl-8">
        <section class="platform-panel"><div class="platform-panel-head"><div><h2>Recent orders</h2><p>Orders from this merchant's shop.</p></div><?php if (!empty($merchant['shop_id'])): ?><a class="btn btn-sm btn-outline-dark" href="<?= e($base) ?>/orders?search=<?= urlencode((string)$merchant['shop_name']) ?>">View orders</a><?php endif; ?></div>
        <div class="table-responsive"><table class="table platform-table align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody>
        <?php if (!$orders): ?><tr><td colspan="6"><div class="platform-empty">No orders yet.</div></td></tr><?php endif; ?>
        <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong><div class="platform-muted"><?= e($order['shop_name']) ?></div></td><td><?= e($order['customer_name']) ?></td><td><?= e($order['currency']) ?> <?= e(number_format((float)$order['total'],2)) ?></td><td><span class="status-badge <?= e(detailStatus($order['payment_status'])) ?>"><?= e(ucfirst((string)$order['payment_status'])) ?></span></td><td><span class="status-badge <?= e(detailStatus($order['status'])) ?>"><?= e(ucfirst((string)$order['status'])) ?></span></td><td class="platform-muted"><?= e(date('d M Y H:i',strtotime((string)$order['created_at']))) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></section>
    </div>
</div>
<?php $content=ob_get_clean(); $title='Merchant'; $platformSection='merchants'; require dirname(__DIR__,2).'/app/Views/layouts/platform.php';
