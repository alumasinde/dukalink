<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Notifications\NotificationTemplateRepository;
use App\Modules\Shops\ShopRepository;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use function App\Support\e;

new App();
$db = Database::connection();
$merchant = Auth::requireMerchant($db);
$shopId = (int)$merchant['id'];
$shop = (new ShopRepository($db))->find($shopId);
$labels = ['order_created' => 'Order received', 'order_confirmed' => 'Order confirmed', 'order_preparing' => 'Order preparing', 'order_ready' => 'Order ready', 'order_delivered' => 'Order delivered', 'order_cancelled' => 'Order cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $event = (string)($_POST['event_key'] ?? '');
        $template = trim((string)($_POST['template'] ?? ''));
        $enabled = isset($_POST['enabled']);
        if (!isset($labels[$event])) Session::flash('error', 'Invalid notification template.');
        elseif ($template === '' || mb_strlen($template) > 1000) Session::flash('error', 'Template must be between 1 and 1000 characters.');
        else {
            try {
                (new NotificationTemplateRepository($db))->update($shopId, $event, $template, $enabled);
                Session::flash('success', 'SMS template updated.');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
        }
    }
    header('Location:/settings/notifications');
    exit;
}
$templates = [];
foreach ((new NotificationTemplateRepository($db))->allForShop($shopId) as $t) $templates[$t['event_key']] = $t;
$merchantShop = $shop;
$merchantSection = 'settings';
ob_start(); ?>
<div class="merchant-shell"><?php require dirname(__DIR__, 2) . '/app/Views/components/merchant-sidebar.php'; ?><main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Notifications</div>
                <h1 class="h3 fw-bold mb-0">Customer SMS</h1>
            </div><a href="/settings/shop" class="btn btn-outline-dark">Shop settings</a>
        </div><?php require dirname(__DIR__, 2) . '/app/Views/components/alert.php'; ?>
        <section class="panel p-4 mb-4">
            <div class="d-flex justify-content-between gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">SMS sender</h2>
                    <p class="small text-secondary mb-0">Customers will receive SMS from the Dukame sender configured on the platform. Merchants cannot change the sender ID.</p>
                </div><span class="badge text-bg-light border align-self-start"><?= e($_ENV['TEXTSMS_SENDER_ID'] ?? $_ENV['APP_NAME'] ?? 'Dukame') ?></span>
            </div>
            <div class="small text-secondary mt-3">Templates are the only SMS setting available to your shop. Dukame handles delivery through TextSMS.</div>
        </section>
        <section class="panel p-4 mb-4">
            <h2 class="h5 fw-bold mb-1">Available placeholders</h2>
            <p class="small text-secondary">Use these tokens in any template. They are replaced automatically when the SMS is sent.</p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['{App Name}', '{Store Name}', '{Customer First Name}', '{Customer Last Name}', '{Customer Name}', '{Order Number}', '{Order Total}', '{Currency}', '{Track URL}', '{Store URL}'] as $token): ?><code class="px-2 py-1 rounded bg-light border small"><?= e($token) ?></code><?php endforeach; ?></div>
        </section>
        <?php foreach ($labels as $event => $label): $t = $templates[$event] ?? ['template' => '', 'enabled' => 1]; ?><section class="panel p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h6 fw-bold mb-1"><?= e($label) ?></h2>
                        <p class="small text-secondary mb-0">Sent when this order event occurs.</p>
                    </div><span class="small text-secondary">SMS</span>
                </div>
                <form method="post" class="mt-3"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="event_key" value="<?= e($event) ?>"><textarea class="form-control" name="template" rows="3" maxlength="1000" required><?= e($t['template']) ?></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-3 gap-3"><label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="enabled" <?= !empty($t['enabled']) ? 'checked' : '' ?>> <span class="form-check-label">Send this SMS</span></label><button class="btn btn-outline-dark">Save template</button></div>
                </form>
            </section><?php endforeach; ?>
    </main>
</div>
<?php $content = ob_get_clean();
require dirname(__DIR__, 2) . '/app/Views/layouts/app.php';
