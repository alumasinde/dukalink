<?php

declare(strict_types=1);

return [
    '' => 'app/Views/home.php',
    'login' => 'app/Http/Pages/Auth/login.php',
    'logout' => 'app/Http/Pages/Auth/logout.php',
    'register' => 'app/Http/Pages/Auth/register.php',
    'onboarding' => 'app/Http/Pages/Auth/onboarding.php',
    'onboarding/complete' => 'app/Http/Pages/Auth/onboarding-complete.php',

    'dashboard' => 'app/Http/Pages/Merchant/dashboard/index.php',
    'products' => 'app/Http/Pages/Merchant/products/index.php',
    'products/create' => 'app/Http/Pages/Merchant/products/create.php',
    'products/edit' => 'app/Http/Pages/Merchant/products/edit.php',
    'categories' => 'app/Http/Pages/Merchant/categories/index.php',
    'orders' => 'app/Http/Pages/Merchant/orders/index.php',
    'orders/view' => 'app/Http/Pages/Merchant/orders/view.php',
    'customers' => 'app/Http/Pages/Merchant/customers/index.php',
    'customers/view' => 'app/Http/Pages/Merchant/customers/view.php',
    'settings' => 'app/Http/Pages/Merchant/settings/index.php',
    'settings/shop' => 'app/Http/Pages/Merchant/settings/shop.php',
    'settings/payments' => 'app/Http/Pages/Merchant/settings/payments.php',
    'settings/delivery' => 'app/Http/Pages/Merchant/settings/delivery.php',
    'settings/notifications' => 'app/Http/Pages/Merchant/settings/notifications.php',
    'subscription' => 'app/Http/Pages/Merchant/subscription/index.php',
    'subscription/pay' => 'app/Http/Pages/Merchant/subscription/pay.php',
    'subscription/status' => 'app/Http/Pages/Merchant/subscription/status.php',

    'cart' => 'app/Http/Pages/Customer/cart/index.php',
    'checkout' => 'app/Http/Pages/Customer/checkout/index.php',
    'track' => 'app/Http/Pages/Customer/track/index.php',
];
