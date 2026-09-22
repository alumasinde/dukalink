<?php

declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\ApiAuth;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;
use App\Database\Database;
use App\Modules\Customers\CustomerRepository;
use App\Modules\Orders\OrderRepository;
use App\Modules\Orders\OrderService;
use App\Modules\Shops\ShopRepository;
use function App\Support\normalize_phone;
use function App\Support\whatsapp_url;

final class OrderController
{
    public static function merchantOrders(): never
    {
        $shop = self::merchantShop();
        $status = trim((string)($_GET['status'] ?? ''));
        JsonResponse::send((new OrderRepository(Database::connection()))->allForShop((int)$shop['id'], $status ?: null));
    }

    public static function merchantOrder(int $id): never
    {
        $shop = self::merchantShop();
        $order = (new OrderRepository(Database::connection()))->findForShop($id, (int)$shop['id']);
        if (!$order) JsonResponse::error('Order not found.', 404, 'order_not_found');
        JsonResponse::send($order);
    }

    public static function updateStatus(int $id): never
    {
        $shop = self::merchantShop();
        $status = (string)(Request::json()['status'] ?? '');
        if (!(new OrderRepository(Database::connection()))->updateStatus($id, (int)$shop['id'], $status)) {
            JsonResponse::error('Invalid status or order not found.', 422, 'invalid_order_status');
        }
        JsonResponse::send(['message' => 'Order status updated.']);
    }

    public static function customers(): never
    {
        $shop = self::merchantShop();
        JsonResponse::send((new CustomerRepository(Database::connection()))->allForShop((int)$shop['id']));
    }

    public static function customer(int $id): never
    {
        $shop = self::merchantShop();
        $repo = new CustomerRepository(Database::connection());
        $customer = $repo->findForShop($id, (int)$shop['id']);
        if (!$customer) JsonResponse::error('Customer not found.', 404, 'customer_not_found');
        $customer['orders'] = $repo->ordersForCustomer($id, (int)$shop['id']);
        JsonResponse::send($customer);
    }

    public static function createPublic(): never
    {
        $data = Request::json();
        $slug = strtolower(trim((string)($data['shop_slug'] ?? '')));
        if ($slug === '') JsonResponse::error('Shop slug is required.', 422, 'validation_error');
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, slug, name, status, phone, currency FROM shops s LEFT JOIN shop_settings ss ON ss.shop_id = s.id WHERE s.slug = :slug AND s.status = "active" LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $shop = $stmt->fetch();
        if (!$shop) JsonResponse::error('Store not found.', 404, 'store_not_found');
        try {
            $order = (new OrderService($db))->create((int)$shop['id'], $data);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422, 'validation_error');
        } catch (\Throwable $e) {
            JsonResponse::error('The order could not be created.', 500, 'order_creation_failed');
        }
        $settings = (new ShopRepository($db))->find((int)$shop['id']);
        $message = self::whatsappMessage($order, $settings['name'] ?? $shop['name']);
        JsonResponse::send([
            'order' => $order,
            'whatsapp_url' => !empty($settings['whatsapp_number']) ? whatsapp_url((string)$settings['whatsapp_number'], $message) : null,
        ], 201);
    }

    public static function track(): never
    {
        $data = Request::json();
        $orderNumber = trim((string)($data['order_number'] ?? ''));
        $phone = normalize_phone((string)($data['phone'] ?? ''));
        if ($orderNumber === '' || $phone === '') JsonResponse::error('Order number and phone number are required.', 422, 'validation_error');
        $stmt = Database::connection()->prepare('SELECT o.id, o.order_number, o.customer_name, o.currency, o.subtotal, o.delivery_fee, o.total, o.status, o.payment_method, o.payment_status, o.delivery_address, o.created_at, s.name AS shop_name, s.slug AS shop_slug, s.logo_path
            FROM orders o INNER JOIN shops s ON s.id = o.shop_id
            WHERE o.order_number = :order_number AND o.customer_phone = :phone AND s.status = "active" LIMIT 1');
        $stmt->execute(['order_number'=>$orderNumber,'phone'=>$phone]);
        $order=$stmt->fetch();
        if(!$order) JsonResponse::error('We could not find an order with those details.',404,'order_not_found');
        JsonResponse::send($order);
    }

    private static function merchantShop(): array
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        return $shop;
    }

    private static function whatsappMessage(array $order, string $shopName): string
    {
        $lines = ["🛍️ NEW ORDER — Dukame", '', 'Store: '.$shopName, 'Order: '.$order['order_number'], '', 'Customer:', $order['customer_name'], '📞 '.$order['customer_phone'], '', 'Items:'];
        foreach (($order['items'] ?? []) as $item) {
            $optionText = '';
            $selected = json_decode($item['selected_options'] ?? '[]', true) ?: [];
            if ($selected) $optionText = ' [' . implode(', ', $selected) . ']';
            $lines[] = $item['product_name'].$optionText.' × '.$item['quantity'].' — '.$order['currency'].' '.number_format((float)$item['line_total'], 2);
        }
        $lines[]=''; $lines[]='TOTAL: '.$order['currency'].' '.number_format((float)$order['total'],2); $lines[]=''; $lines[]='Track order: '.rtrim((string)($_ENV['APP_URL']??''),'/').'/track'; $lines[]=''; $lines[]='Manage this order: '.rtrim((string)($_ENV['APP_URL']??''),'/').'/orders/view?id='.$order['id'];
        return implode("\n", $lines);
    }
}
