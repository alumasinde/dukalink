<?php

declare(strict_types=1);

use App\Database\Database;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    $db = Database::connection();
    $db->query('SELECT 1');
    echo json_encode([
        'status' => 'ok',
        'service' => 'dukame',
        'checks' => ['database' => 'ok'],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'degraded',
        'service' => 'dukame',
        'checks' => ['database' => 'failed'],
    ], JSON_UNESCAPED_SLASHES);
}
