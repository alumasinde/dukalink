<?php declare(strict_types=1); require dirname(__DIR__,2).'/vendor/autoload.php'; use function App\Support\e; $title='Your Cart'; ob_start(); ?>
<div class="customer-shell-page"><header class="simple-customer-header"><div class="customer-container"><a href="javascript:history.back()" class="customer-back">Continue shopping</a><strong>Your cart</strong><a href="/track" class="simple-header-link">Track order</a></div></header><main class="customer-container customer-cart-page"><div id="cartRoot"><div class="customer-loading">Loading cart…</div></div></main></div>
<script>window.DUKAME_CART_PAGE=true;</script>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/Views/layouts/customer.php';
