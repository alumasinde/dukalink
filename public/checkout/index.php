<?php declare(strict_types=1); require dirname(__DIR__,2).'/vendor/autoload.php'; use function App\Support\e; $title='Checkout'; ob_start(); ?>
<div class="customer-shell-page"><header class="simple-customer-header"><div class="customer-container"><a href="/cart" class="customer-back">Cart</a><strong>Checkout</strong><span></span></div></header><main class="customer-container customer-checkout-page"><div id="checkoutRoot"><div class="customer-loading">Loading checkout…</div></div></main></div>
<script>window.DUKAME_CHECKOUT_PAGE=true;</script>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/Views/layouts/customer.php';
