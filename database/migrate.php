<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;

$app = new App();
$db = Database::connection();

$migrations = [
    __DIR__ . '/migrations/001_create_users.sql',
    __DIR__ . '/migrations/002_create_shops.sql',
    __DIR__ . '/migrations/003_create_categories_products.sql',
    __DIR__ . '/migrations/004_add_shop_branding_and_mpesa.sql',
    __DIR__ . '/migrations/005_create_api_tokens.sql',
    __DIR__ . '/migrations/006_create_orders_customers.sql',
    __DIR__ . '/migrations/007_add_product_options.sql',
];

foreach ($migrations as $file) {
    echo "Running " . basename($file) . "...\n";
    $sql = file_get_contents($file);
    $db->exec($sql);
}

echo "Migration complete.\n";
