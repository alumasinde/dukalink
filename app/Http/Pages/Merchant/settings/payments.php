<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use function App\Support\e;

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
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'KES')));
        $allowedCurrencies = ['KES', 'USD', 'UGX', 'TZS'];
        $orderPrefix = strtoupper(trim((string)($_POST['order_number_prefix'] ?? 'DK')));
        $nextOrderNumber = (int)($_POST['next_order_number'] ?? 1001);
        $whatsappNumber = trim((string)($_POST['whatsapp_number'] ?? ''));
        $mpesaPhone = trim((string)($_POST['mpesa_phone'] ?? ''));
        $environment = ($_POST['mpesa_environment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $shortcode = trim((string)($_POST['mpesa_shortcode'] ?? ''));
        $consumerKey = trim((string)($_POST['mpesa_consumer_key'] ?? ''));
        $consumerSecret = trim((string)($_POST['mpesa_consumer_secret'] ?? ''));
        $passkey = trim((string)($_POST['mpesa_passkey'] ?? ''));
        $mpesaEnabled = isset($_POST['mpesa_enabled']);
        $codEnabled = isset($_POST['allow_cash_on_delivery']);

        $errors = [];
        if (!in_array($currency, $allowedCurrencies, true)) $errors[] = 'Please select a supported currency.';
        if ($orderPrefix === '' || !preg_match('/^[A-Z0-9]{1,12}$/', $orderPrefix)) $errors[] = 'Order number prefix must contain 1–12 letters or numbers.';
        if ($nextOrderNumber < 1) $errors[] = 'Next order number must be at least 1.';

        $hasSavedCredentials = !empty($shop['mpesa_credentials_configured']);
        $hasCredentialsAfterSave = $hasSavedCredentials || ($consumerKey !== '' && $consumerSecret !== '' && $passkey !== '');
        if ($mpesaEnabled) {
            if ($currency !== 'KES') $errors[] = 'M-Pesa is currently available only when your store currency is KES.';
            if ($mpesaPhone === '') $errors[] = 'Enter the M-Pesa number that will receive customer payments.';
            if ($shortcode === '') $errors[] = 'Enter your M-Pesa Business Short Code.';
            if (!$hasCredentialsAfterSave) $errors[] = 'Enter all M-Pesa Daraja credentials before enabling M-Pesa.';
        }

        if (!$errors) {
            $repo->updateSettings($shopId, [
                'currency' => $currency,
                'order_number_prefix' => $orderPrefix,
                'next_order_number' => $nextOrderNumber,
                'whatsapp_number' => $whatsappNumber,
                'mpesa_phone' => $mpesaPhone,
                'mpesa_enabled' => $mpesaEnabled,
                'mpesa_environment' => $environment,
                'mpesa_shortcode' => $shortcode,
                'mpesa_consumer_key' => $consumerKey,
                'mpesa_consumer_secret' => $consumerSecret,
                'mpesa_passkey' => $passkey,
                'allow_cash_on_delivery' => $codEnabled,
            ]);
            Session::flash('success', 'Payment settings updated.');
        } else {
            Session::flash('error', implode(' ', $errors));
        }
    }

    header('Location: /settings/payments');
    exit;
}

$shop = $repo->find($shopId);
$merchantShop = $shop;
$merchantSection = 'payments';
ob_start();
?>
<div class="merchant-shell">
    <?php require $root . '/app/Views/components/merchant-sidebar.php'; ?>
    <main class="merchant-main">
        <div class="merchant-topbar">
            <div>
                <div class="small text-secondary">Settings</div>
                <h1 class="h3 fw-bold mb-0">Payments</h1>
                <p class="small text-secondary mt-1 mb-0">Choose how customers can pay when they place an order.</p>
            </div>
            <a href="<?= e(\App\Support\shop_url($shop['slug'])) ?>" target="_blank" class="btn btn-outline-dark">View storefront</a>
        </div>

        <?php require $root . '/app/Views/components/alert.php'; ?>

        <form method="post">
            <?= Csrf::field() ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <section class="panel p-4 mb-4">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">M-Pesa</h2>
                                <p class="small text-secondary mb-0">Customers receive an STK Push on their phone and pay directly from checkout.</p>
                            </div>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" name="mpesa_enabled" id="mpesaEnabled" <?= !empty($shop['mpesa_enabled']) ? 'checked' : '' ?>>
                                <label class="visually-hidden" for="mpesaEnabled">Enable M-Pesa</label>
                            </div>
                        </div>

                        <div class="payment-settings-status mt-3 <?= !empty($shop['mpesa_enabled']) ? 'is-enabled' : '' ?>">
                            <strong><?= !empty($shop['mpesa_enabled']) ? 'M-Pesa is enabled' : 'M-Pesa is disabled' ?></strong>
                            <span><?= !empty($shop['mpesa_enabled']) ? 'Customers can choose M-Pesa at checkout.' : 'Customers will not see M-Pesa at checkout.' ?></span>
                        </div>

                        <div class="mt-4">
                            <div class="small fw-bold text-uppercase text-secondary mb-3">Daraja connection</div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">M-Pesa number</label>
                                <input class="form-control" name="mpesa_phone" value="<?= e($shop['mpesa_phone'] ?? '') ?>" placeholder="0712 345 678">
                                <div class="form-text">The M-Pesa number or Till/Paybill receiving the payment, depending on your Daraja setup.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Environment</label>
                                <select class="form-select" name="mpesa_environment">
                                    <option value="sandbox" <?= ($shop['mpesa_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                    <option value="production" <?= ($shop['mpesa_environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Business Short Code</label>
                                <input class="form-control" name="mpesa_shortcode" value="<?= e($shop['mpesa_shortcode'] ?? '') ?>" placeholder="174379">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Consumer Key</label>
                                <input class="form-control" name="mpesa_consumer_key" placeholder="<?= !empty($shop['mpesa_credentials_configured']) ? 'Saved. Leave blank to keep it.' : 'Paste Consumer Key' ?>" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Consumer Secret</label>
                                <input class="form-control" type="password" name="mpesa_consumer_secret" placeholder="<?= !empty($shop['mpesa_credentials_configured']) ? 'Saved. Leave blank to keep it.' : 'Paste Consumer Secret' ?>" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">STK Passkey</label>
                                <input class="form-control" type="password" name="mpesa_passkey" placeholder="<?= !empty($shop['mpesa_credentials_configured']) ? 'Saved. Leave blank to keep it.' : 'Paste STK Passkey' ?>" autocomplete="new-password">
                            </div>
                            <?php if (!empty($shop['mpesa_credentials_configured'])): ?>
                                <div class="alert alert-success py-2 small mb-0">Daraja credentials are saved securely.</div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="panel p-4 mb-4">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">Cash on delivery</h2>
                                <p class="small text-secondary mb-0">Let customers place an order and pay when the order is delivered.</p>
                            </div>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" name="allow_cash_on_delivery" id="codEnabled" <?= !empty($shop['allow_cash_on_delivery']) ? 'checked' : '' ?>>
                                <label class="visually-hidden" for="codEnabled">Enable cash on delivery</label>
                            </div>
                        </div>
                    </section>

                    <section class="panel p-4 mb-4 border-primary-subtle">
                        <h2 class="h6 fw-bold mb-1">Delivery and pickup</h2>
                        <p class="small text-secondary mb-3">Payment methods are separate from how the customer receives the order.</p>
                        <a class="btn btn-outline-dark w-100" href="/settings/delivery">Manage delivery & pickup</a>
                    </section>

                    <section class="panel p-4">
                        <h2 class="h5 fw-bold mb-1">What customers will see</h2>
                        <p class="small text-secondary">Checkout only displays payment methods you enable here.</p>
                        <div class="payment-method-preview mt-3">
                            <div><span>M-Pesa</span><strong><?= !empty($shop['mpesa_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div>
                            <div><span>Cash on delivery</span><strong><?= !empty($shop['allow_cash_on_delivery']) ? 'Enabled' : 'Disabled' ?></strong></div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-5">
                    <section class="panel p-4 mb-4">
                        <h2 class="h5 fw-bold">Checkout defaults</h2>
                        <p class="small text-secondary">These settings are used when customers place orders.</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Currency</label>
                            <select class="form-select" name="currency">
                                <?php foreach (['KES','USD','UGX','TZS'] as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= ($shop['currency'] ?? 'KES') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Order number prefix</label>
                            <input class="form-control" name="order_number_prefix" value="<?= e($shop['order_number_prefix'] ?? 'DK') ?>" maxlength="12" pattern="[A-Za-z0-9]{1,12}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Next order number</label>
                            <input class="form-control" type="number" name="next_order_number" value="<?= e($shop['next_order_number'] ?? 1001) ?>" min="1" required>
                        </div>
                        <div>
                            <label class="form-label fw-semibold">WhatsApp number</label>
                            <input class="form-control" name="whatsapp_number" value="<?= e($shop['whatsapp_number'] ?? '') ?>" placeholder="0712 345 678">
                            <div class="form-text">Used for the optional Order on WhatsApp action.</div>
                        </div>
                    </section>

                    <div class="panel p-4 border-primary-subtle">
                        <h2 class="h6 fw-bold">Payment setup</h2>
                        <p class="small text-secondary mb-3">Enable at least one payment method before customers can complete checkout.</p>
                        <button class="btn btn-primary w-100" type="submit">Save payment settings</button>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('mpesaEnabled');
    const status = document.querySelector('.payment-settings-status');
    const update = () => {
        if (!toggle || !status) return;
        status.classList.toggle('is-enabled', toggle.checked);
        status.querySelector('strong').textContent = toggle.checked ? 'M-Pesa is enabled' : 'M-Pesa is disabled';
        status.querySelector('span').textContent = toggle.checked ? 'Customers can choose M-Pesa at checkout.' : 'Customers will not see M-Pesa at checkout.';
    };
    toggle?.addEventListener('change', update);
});
</script>
<?php
$content = ob_get_clean();
$title = 'Payments';
$merchantLayout = true;
require $root . '/app/Views/layouts/app.php';
