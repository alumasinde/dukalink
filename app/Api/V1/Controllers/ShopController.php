<?php
declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\ApiAuth;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use function App\Support\slugify;
use function App\Support\reserved_shop_slug;

final class ShopController
{
    public static function current(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('No active shop found for this account.', 404, 'shop_not_found');

        $shop = (new ShopRepository(Database::connection()))->find((int)$shop['id']);
        JsonResponse::send($shop);
    }

    public static function update(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');

        $data = Request::json();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') JsonResponse::error('Shop name is required.', 422, 'validation_error');

        $repo = new ShopRepository(Database::connection());
        $repo->update((int)$shop['id'], [
            'name' => $name,
            'business_type' => trim((string)($data['business_type'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
        ]);

        JsonResponse::send($repo->find((int)$shop['id']));
    }

    public static function updateSlug(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');

        $slug = slugify((string)(Request::json()['slug'] ?? ''));
        if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 80 || reserved_shop_slug($slug)) {
            JsonResponse::error('Invalid or reserved shop link.', 422, 'invalid_slug');
        }

        $repo = new ShopRepository(Database::connection());
        if ($repo->slugExists($slug, (int)$shop['id'])) {
            JsonResponse::error('That shop link is already taken.', 409, 'slug_taken');
        }

        $repo->updateSlug((int)$shop['id'], $slug);
        JsonResponse::send(['slug' => $slug, 'url' => '/' . $slug]);
    }

    public static function updateSettings(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');

        $data = Request::json();
        $currency = strtoupper(trim((string)($data['currency'] ?? 'KES')));
        if (!in_array($currency, ['KES','USD','UGX','TZS'], true)) {
            JsonResponse::error('Unsupported currency.', 422, 'validation_error');
        }

        $prefix = strtoupper(trim((string)($data['order_number_prefix'] ?? 'DK')));
        $nextOrderNumber = (int)($data['next_order_number'] ?? 1001);
        if ($prefix === '' || !preg_match('/^[A-Z0-9]{1,12}$/', $prefix) || $nextOrderNumber < 1) {
            JsonResponse::error('Invalid order number settings.', 422, 'validation_error');
        }

        $repo = new ShopRepository(Database::connection());
        $repo->updateSettings((int)$shop['id'], [
            'currency' => $currency,
            'order_number_prefix' => $prefix,
            'next_order_number' => $nextOrderNumber,
            'whatsapp_number' => trim((string)($data['whatsapp_number'] ?? '')),
            'mpesa_phone' => trim((string)($data['mpesa_phone'] ?? '')),
            'mpesa_environment' => in_array(($data['mpesa_environment'] ?? 'sandbox'), ['sandbox','production'], true) ? $data['mpesa_environment'] : 'sandbox',
            'mpesa_shortcode' => trim((string)($data['mpesa_shortcode'] ?? '')),
            'mpesa_consumer_key' => trim((string)($data['mpesa_consumer_key'] ?? '')),
            'mpesa_consumer_secret' => trim((string)($data['mpesa_consumer_secret'] ?? '')),
            'mpesa_passkey' => trim((string)($data['mpesa_passkey'] ?? '')),
            'allow_cash_on_delivery' => !empty($data['allow_cash_on_delivery']),
        ]);
        JsonResponse::send($repo->find((int)$shop['id']));
    }

}
