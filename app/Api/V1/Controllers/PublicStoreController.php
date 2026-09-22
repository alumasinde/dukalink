<?php
declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\JsonResponse;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Shops\ShopRepository;

final class PublicStoreController
{
    public static function show(string $slug): never
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, name, slug, business_type, description, phone, logo_path, status FROM shops WHERE slug = :slug AND status = "active" LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $shop = $stmt->fetch();
        if (!$shop) JsonResponse::error('Store not found.', 404, 'store_not_found');

        $shopId = (int)$shop['id'];
        $settings = (new ShopRepository($db))->find($shopId) ?: [];
        $categories = (new CategoryRepository($db))->allForShop($shopId, true);
        $products = (new ProductRepository($db))->allForShop($shopId, 'active');
        $currency = $settings['currency'] ?? 'KES';
        $mpesa = !empty($settings['mpesa_enabled']) && !empty($settings['mpesa_credentials_configured']) && !empty($settings['mpesa_phone']) && $currency === 'KES';
        $delivery = !empty($settings['allow_delivery']);
        $pickup = !empty($settings['allow_store_pickup']);
        $zones = $delivery && (($settings['delivery_pricing_mode'] ?? 'flat') === 'zone')
            ? (new ShopRepository($db))->deliveryZones($shopId, true)
            : [];

        JsonResponse::send([
            'shop' => $shop,
            'categories' => $categories,
            'products' => $products,
            'payment_methods' => [
                'mpesa' => $mpesa,
                'cash_on_delivery' => $delivery && !empty($settings['allow_cash_on_delivery']),
                'cash_on_pickup' => $pickup && !empty($settings['allow_cash_on_pickup']),
            ],
            'fulfillment' => [
                'delivery' => $delivery,
                'pickup' => $pickup,
                'pricing_mode' => $settings['delivery_pricing_mode'] ?? 'flat',
                'flat_fee' => (float)($settings['delivery_flat_fee'] ?? 0),
                'free_delivery_minimum' => $settings['free_delivery_minimum'] !== null ? (float)$settings['free_delivery_minimum'] : null,
                'zones' => $zones,
                'pickup_address' => $settings['pickup_address'] ?? null,
                'pickup_instructions' => $settings['pickup_instructions'] ?? null,
            ],
        ]);
    }
}
