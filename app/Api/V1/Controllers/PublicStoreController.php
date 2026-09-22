<?php
declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\JsonResponse;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Shops\ShopRepository;
use PDO;

final class PublicStoreController
{
    public static function show(string $slug): never
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, name, slug, business_type, description, phone, logo_path, status FROM shops WHERE slug = :slug AND status = "active" LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $shop = $stmt->fetch();
        if (!$shop) JsonResponse::error('Store not found.', 404, 'store_not_found');

        $repo = new ProductRepository($db);
        $categories = (new CategoryRepository($db))->allForShop((int)$shop['id'], true);
        $products = $repo->allForShop((int)$shop['id'], 'active');

        $settings=(new ShopRepository($db))->find((int)$shop['id']);
        $mpesa=($shop['status']==='active') && !empty($settings['mpesa_credentials_configured']) && !empty($settings['mpesa_phone']) && (($settings['currency']??'KES')==='KES');
        $cod=!empty($settings['allow_cash_on_delivery']);
        JsonResponse::send([
            'shop' => $shop,
            'categories' => $categories,
            'products' => $products,
            'payment_methods' => ['mpesa'=>$mpesa,'cash_on_delivery'=>$cod],
        ]);
    }
}
