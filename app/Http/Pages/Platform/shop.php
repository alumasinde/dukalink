<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Platform\PlatformRepository;
use App\Modules\Platform\PlatformControlService;
use App\Modules\Platform\PlatformAuditService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\PlatformAuth;
use function App\Support\e;

new App();
$db = Database::connection();
$platformUser = PlatformAuth::requireAdmin($db);
$repo = new PlatformRepository($db);
$control = new PlatformControlService($db);
$audit = new PlatformAuditService($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($platformUser['role'] ?? '') !== 'super_admin') { Session::flash('error', 'Only a super administrator can change shop controls.'); header('Location: '.PlatformAuth::basePath().'/shops/view/'.(int)($_GET['id'] ?? 0)); exit; }
    if (!Csrf::verify($_POST['_csrf'] ?? null)) { Session::flash('error', 'Your form session expired. Please try again.'); header('Location: '.PlatformAuth::basePath().'/shops/view/'.(int)($_GET['id'] ?? 0)); exit; }
    try {
        $action=(string)($_POST['action'] ?? ''); $target=(int)($_POST['id'] ?? 0);
        if ($action === 'shop_status') { $status=(string)($_POST['status'] ?? ''); $control->setShopStatus($target,$status); $audit->record((int)$platformUser['id'],'shop.status_changed','Changed shop status','shop',$target,['status'=>$status]); Session::flash('success','Shop status updated.'); }
        elseif ($action === 'subscription_activate') { $control->activateSubscription($target,(int)($_POST['months'] ?? 1)); $audit->record((int)$platformUser['id'],'subscription.activated','Activated subscription manually','subscription',$target,['months'=>(int)($_POST['months'] ?? 1)]); Session::flash('success','Subscription activated.'); }
        elseif ($action === 'subscription_cancel') { $control->cancelSubscription($target); $audit->record((int)$platformUser['id'],'subscription.cancelled','Cancelled subscription','subscription',$target); Session::flash('success','Subscription cancelled.'); }
    } catch (Throwable $e) { Session::flash('error','Could not apply control: '.$e->getMessage()); }
    header('Location: '.PlatformAuth::basePath().'/shops/view/'.(int)($_GET['id'] ?? 0)); exit;
}
$error=Session::consumeFlash('error'); $success=Session::consumeFlash('success');
$id = max(1, (int)($_GET['id'] ?? 0));
$shop = $repo->shopDetail($id);
if (!$shop) { http_response_code(404); require $root . '/404.php'; exit; }
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
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2>Platform controls</h2><p>Operational controls require a super administrator.</p></div></div><div class="p-3"><div class="platform-action-bar"><form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="shop_status"><input type="hidden" name="id" value="<?= (int)$shop['id'] ?>"><input type="hidden" name="status" value="<?= $shop['status']==='active'?'suspended':'active' ?>"><button class="btn btn-sm <?= $shop['status']==='active'?'btn-outline-danger':'btn-primary' ?>" <?= $platformUser['role']!=='super_admin'?'disabled':'' ?>><?= $shop['status']==='active'?'Suspend shop':'Activate shop' ?></button></form><?php if(!empty($shop['subscription_id'])): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="subscription_activate"><input type="hidden" name="id" value="<?= (int)$shop['subscription_id'] ?>"><input type="hidden" name="months" value="1"><button class="btn btn-sm btn-outline-dark" <?= $platformUser['role']!=='super_admin'?'disabled':'' ?>>Activate 1 month</button></form><?php if(($shop['subscription_status']??'')!=='cancelled'): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="subscription_cancel"><input type="hidden" name="id" value="<?= (int)$shop['subscription_id'] ?>"><button class="btn btn-sm btn-outline-danger" <?= $platformUser['role']!=='super_admin'?'disabled':'' ?>>Cancel subscription</button></form><?php endif; ?><?php endif; ?></div><p class="platform-action-note mt-2">Manual subscription activation is for support/recovery cases. Normal billing remains M-Pesa-confirmed.</p></div></section>
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
<?php $content=ob_get_clean(); $title='Shop'; $platformSection='shops'; require $root.'/app/Views/layouts/platform.php';
