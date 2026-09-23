<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Platform\PlatformRepository;
use App\Modules\Subscriptions\SubscriptionPlanRepository;
use App\Support\PlatformAuth;
use function App\Support\e;

new App();
$db = Database::connection();
$platformUser = PlatformAuth::requireAdmin($db);
$repo = new PlatformRepository($db);
$metrics = $repo->metrics();
$merchants = $repo->recentMerchants(7);
$plans = (new SubscriptionPlanRepository($db))->all();

function platformStatusClass(?string $status): string {
    return match ($status) {
        'active', 'paid' => 'status-active',
        'trial' => 'status-trial',
        'past_due' => 'status-past_due',
        'expired', 'suspended', 'cancelled', 'failed' => 'status-expired',
        default => 'status-draft',
    };
}

ob_start(); ?>
<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Merchants', $metrics['merchants'], 'Registered merchant accounts', '♙'],
        ['Shops', $metrics['shops'], $metrics['active_shops'].' active · '.$metrics['draft_shops'].' draft', '▣'],
        ['Active subscriptions', $metrics['active_subscriptions'], $metrics['trials'].' currently on trial', '◉'],
        ['Subscription revenue', 'KSh '.number_format($metrics['subscription_revenue'], 0), $metrics['past_due_subscriptions'].' past due', '₵'],
    ];
    foreach ($stats as [$label,$value,$meta,$icon]): ?>
    <div class="col-sm-6 col-xl-3"><div class="platform-stat"><div class="d-flex justify-content-between align-items-start"><div class="label"><?= e($label) ?></div><div class="platform-stat-icon"><?= e($icon) ?></div></div><div class="value"><?= e((string)$value) ?></div><div class="meta"><?= e($meta) ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="platform-panel">
            <div class="platform-panel-head"><div><h2>Recent merchants</h2><p>New merchant accounts and their current shop subscription.</p></div><a class="btn btn-sm btn-outline-dark" href="<?= e(PlatformAuth::basePath()) ?>/merchants">View all</a></div>
        <?php if (!$merchants): ?><div class="platform-empty">No merchants have registered yet.</div><?php else: ?>
        <div class="table-responsive"><table class="table platform-table align-middle mb-0"><thead><tr><th>Merchant</th><th>Shop</th><th>Plan</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($merchants as $merchant): ?>
        <tr>
            <td><div class="fw-semibold"><?= e(trim(($merchant['first_name'] ?? '').' '.($merchant['last_name'] ?? '')) ?: 'Merchant') ?></div><div class="platform-muted"><?= e($merchant['phone'] ?? $merchant['email'] ?? '') ?></div></td>
            <td><?php if (!empty($merchant['shop_id'])): ?><div class="fw-semibold"><?= e($merchant['shop_name']) ?></div><div class="platform-muted">/<?= e($merchant['slug']) ?></div><?php else: ?><span class="platform-muted">No shop yet</span><?php endif; ?></td>
            <td><?= e($merchant['plan_name'] ?? '—') ?></td>
            <td><span class="status-badge <?= e(platformStatusClass($merchant['subscription_status'] ?? null)) ?>"><?= e(ucwords(str_replace('_',' ',(string)($merchant['subscription_status'] ?? 'not set')))) ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-light border" href="<?= e(PlatformAuth::basePath()) ?>/merchants/view/<?= (int)$merchant['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2>Subscription health</h2><p>Current platform billing state.</p></div></div><div class="p-3">
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="platform-muted">Active</span><strong><?= $metrics['active_subscriptions'] ?></strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="platform-muted">Trial</span><strong><?= $metrics['trials'] ?></strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="platform-muted">Past due</span><strong><?= $metrics['past_due_subscriptions'] ?></strong></div>
            <div class="d-flex justify-content-between py-2"><span class="platform-muted">Expired</span><strong><?= $metrics['expired_subscriptions'] ?></strong></div>
        </div></section>
        <section class="platform-panel"><div class="platform-panel-head"><div><h2>Plans</h2><p><?= count($plans) ?> configured plan<?= count($plans)===1?'':'s' ?>.</p></div><a class="btn btn-sm btn-outline-dark" href="<?= e(PlatformAuth::basePath()) ?>/plans">Manage</a></div><div class="list-group list-group-flush">
        <?php foreach (array_slice($plans,0,4) as $plan): ?><div class="list-group-item d-flex justify-content-between align-items-center py-3"><div><strong><?= e($plan['name']) ?></strong><div class="platform-muted">KSh <?= e(number_format((float)$plan['monthly_price'],0)) ?> / month</div></div><span class="status-badge <?= $plan['is_active']?'status-active':'status-draft' ?>"><?= $plan['is_active']?'Active':'Inactive' ?></span></div><?php endforeach; ?>
        </div></section>
    </div>
</div>
<?php $content=ob_get_clean(); $title='Dashboard'; $platformSection='overview'; require $root.'/app/Views/layouts/platform.php';
