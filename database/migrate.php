<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;

$app = new App();
$db = Database::connection();

$migrationsDir = __DIR__ . '/migrations';

$files = glob($migrationsDir . '/*.sql');

if ($files === false) {
    throw new RuntimeException('Unable to read migrations directory.');
}

/*
 * Migration filenames should start with their sequence number:
 *
 * 001_create_users.sql
 * 002_create_shops.sql
 * 003_create_categories_products.sql
 * ...
 *
 * Natural sorting ensures 010 comes after 009 and before 011.
 */
natsort($files);

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }

    echo 'Running ' . basename($file) . "...\n";

    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException(
            'Unable to read migration: ' . basename($file)
        );
    }

    $sql = trim($sql);

    if ($sql === '') {
        echo "  Skipping empty migration.\n";
        continue;
    }

    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Migration failed: ' . basename($file) . PHP_EOL .
            $e->getMessage(),
            (int) $e->getCode(),
            $e
        );
    }
}

echo "Migration complete.\n";