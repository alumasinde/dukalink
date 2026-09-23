<?php
declare(strict_types=1);

namespace App\Api\V1;

use App\Api\V1\Controllers\AuthController;
use App\Api\V1\Controllers\ShopController;
use App\Api\V1\Controllers\CatalogController;
use App\Api\V1\Controllers\PublicStoreController;
use App\Api\V1\Controllers\OrderController;
use App\Api\V1\Controllers\SubscriptionController;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;

final class Router
{
    public static function dispatch(): never
    {
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
        $parts = explode('/', $path);

        // /api/v1/...
        if (($parts[0] ?? '') !== 'api' || ($parts[1] ?? '') !== 'v1') {
            JsonResponse::error('API route not found.', 404, 'not_found');
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $maxBody = max(1024, (int)($_ENV['API_MAX_BODY_BYTES'] ?? 2097152));
        if ($contentLength > $maxBody) {
            JsonResponse::error('Request body is too large.', 413, 'payload_too_large');
        }
        $route = implode('/', array_slice($parts, 2));

        switch (true) {
            case $route === 'auth/login' && $method === 'POST':
                AuthController::login();
            case $route === 'auth/me' && $method === 'GET':
                AuthController::me();
            case $route === 'auth/logout' && $method === 'POST':
                AuthController::logout();

            case $route === 'shop' && $method === 'GET':
                ShopController::current();
            case $route === 'shop' && $method === 'PUT':
                ShopController::update();
            case $route === 'shop/slug' && $method === 'PUT':
                ShopController::updateSlug();
            case $route === 'shop/settings' && $method === 'PUT':
                ShopController::updateSettings();
            case $route === 'shop/notification-templates' && $method === 'GET':
                ShopController::notificationTemplates();
            case $route === 'shop/notification-templates' && $method === 'PUT':
                ShopController::updateNotificationTemplate();

            case $route === 'shop/publish' && $method === 'POST':
                ShopController::publish();
            case $route === 'shop/unpublish' && $method === 'POST':
                ShopController::unpublish();

            case $route === 'products' && $method === 'GET':
                CatalogController::products();
            case $route === 'products' && $method === 'POST':
                CatalogController::createProduct();
            case preg_match('#^products/(\d+)$#', $route, $m) === 1 && $method === 'GET':
                CatalogController::product(Request::integer($m[1]));
            case preg_match('#^products/(\d+)$#', $route, $m) === 1 && $method === 'PUT':
                CatalogController::updateProduct(Request::integer($m[1]));
            case preg_match('#^products/(\d+)$#', $route, $m) === 1 && $method === 'DELETE':
                CatalogController::deleteProduct(Request::integer($m[1]));

            case $route === 'categories' && $method === 'GET':
                CatalogController::categories();
            case $route === 'categories' && $method === 'POST':
                CatalogController::createCategory();
            case preg_match('#^categories/(\d+)$#', $route, $m) === 1 && $method === 'PUT':
                CatalogController::updateCategory(Request::integer($m[1]));
            case preg_match('#^categories/(\d+)$#', $route, $m) === 1 && $method === 'DELETE':
                CatalogController::deleteCategory(Request::integer($m[1]));

            case $route === 'orders' && $method === 'GET':
                OrderController::merchantOrders();
            case $route === 'orders' && $method === 'POST':
                OrderController::createPublic();
            case preg_match('#^orders/(\d+)$#', $route, $m) === 1 && $method === 'GET':
                OrderController::merchantOrder(Request::integer($m[1]));
            case preg_match('#^orders/(\d+)/status$#', $route, $m) === 1 && $method === 'PUT':
                OrderController::updateStatus(Request::integer($m[1]));
            case $route === 'orders/track' && $method === 'POST':
                OrderController::track();
            case $route === 'orders/payment-status' && $method === 'POST':
                OrderController::paymentStatus();
            case $route === 'orders/mpesa/retry' && $method === 'POST':
                OrderController::retryMpesa();
            case $route === 'payments/mpesa/callback' && $method === 'POST':
                OrderController::mpesaCallback();
            case $route === 'subscriptions/mpesa/callback' && $method === 'POST':
                SubscriptionController::callback();
            case $route === 'subscriptions/payment/status' && $method === 'GET':
                SubscriptionController::status();
            case $route === 'customers' && $method === 'GET':
                OrderController::customers();
            case preg_match('#^customers/(\d+)$#', $route, $m) === 1 && $method === 'GET':
                OrderController::customer(Request::integer($m[1]));

            case preg_match('#^stores/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $route, $m) === 1 && $method === 'GET':
                PublicStoreController::show($m[1]);
        }

        JsonResponse::error('API route not found.', 404, 'not_found');
    }
}
