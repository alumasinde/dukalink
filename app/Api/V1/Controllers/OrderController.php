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
use App\Modules\Notifications\NotificationService;
use App\Modules\Payments\MpesaService;
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
        $db = Database::connection();
        $repo = new OrderRepository($db);
        $before = $repo->findForShop($id, (int)$shop['id']);
        if (!$before) JsonResponse::error('Order not found.', 404, 'order_not_found');
        if ($before['status'] === $status) JsonResponse::send(['message' => 'Order status is already '.$status.'.']);
        if (!$repo->updateStatus($id, (int)$shop['id'], $status)) {
            JsonResponse::error('Invalid status or order not found.', 422, 'invalid_order_status');
        }
        $order = $repo->findForShop($id, (int)$shop['id']) ?: $before;
        $order['shop_name'] = $shop['name'];
        $order['shop_slug'] = $shop['slug'];
        $eventMap = ['confirmed'=>'order_confirmed','preparing'=>'order_preparing','ready'=>'order_ready','delivered'=>'order_delivered','cancelled'=>'order_cancelled','rejected'=>'order_cancelled'];
        if (isset($eventMap[$status])) (new NotificationService($db))->orderEvent($order, $eventMap[$status]);
        JsonResponse::send(['message' => 'Order status updated.', 'order' => $order]);
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
        $stmt = $db->prepare('SELECT s.id, s.slug, s.name, s.status, s.phone, ss.currency FROM shops AS s LEFT JOIN shop_settings AS ss ON ss.shop_id = s.id WHERE s.slug = :slug AND s.status = "active" LIMIT 1');
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
        $order['shop_name'] = $settings['name'] ?? $shop['name'];
        $order['shop_slug'] = $settings['slug'] ?? $shop['slug'];
        (new NotificationService($db))->orderEvent($order, 'order_created');
        $mpesa = null;
        if (($order['payment_method'] ?? '') === 'mpesa') {
            try {
                $mpesa = (new MpesaService($db))->initiate((int)$shop['id'], (int)$order['id'], (float)$order['total'], (string)$order['customer_phone'], (string)$order['currency'], (string)$order['order_number']);
            } catch (\Throwable $e) {
                $u=$db->prepare('UPDATE orders SET payment_status="failed" WHERE id=:id'); $u->execute(['id'=>$order['id']]);
                $mpesa=['status'=>'failed','message'=>$e->getMessage()];
            }
        }
        $message = self::whatsappMessage($order, $order['shop_name']);
        JsonResponse::send([
            'order' => $order,
            'whatsapp_url' => !empty($settings['whatsapp_number']) ? whatsapp_url((string)$settings['whatsapp_number'], $message) : null,
            'mpesa' => $mpesa ? ['status'=>$mpesa['status'] ?? 'pending','message'=>$mpesa['message'] ?? 'STK Push request sent.'] : null,
        ], 201);
    }

    public static function track(): never
    {
        $data = Request::json();
        $orderNumber = trim((string)($data['order_number'] ?? ''));
        $phone = normalize_phone((string)($data['phone'] ?? ''));
        if ($orderNumber === '' || $phone === '') JsonResponse::error('Order number and phone number are required.', 422, 'validation_error');
        $stmt = Database::connection()->prepare('SELECT o.id, o.order_number, o.customer_name, o.currency, o.subtotal, o.delivery_fee, o.total, o.status, o.payment_method, o.payment_status, o.delivery_address, o.fulfillment_method, o.delivery_zone_name, o.created_at, s.name AS shop_name, s.slug AS shop_slug, s.logo_path
            FROM orders o INNER JOIN shops s ON s.id = o.shop_id
            WHERE o.order_number = :order_number AND o.customer_phone = :phone AND s.status = "active" LIMIT 1');
        $stmt->execute(['order_number'=>$orderNumber,'phone'=>$phone]);
        $order=$stmt->fetch();
        if(!$order) JsonResponse::error('We could not find an order with those details.',404,'order_not_found');
        JsonResponse::send($order);
    }

    public static function mpesaCallback(): never
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        try { (new MpesaService(Database::connection()))->handleCallback(is_array($payload) ? $payload : []); } catch (\Throwable $e) { error_log('M-Pesa callback: '.$e->getMessage()); }
        header('Content-Type: application/json');
        echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);
        exit;
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
        $customerName = trim((string)($order['customer_first_name'] ?? '') . ' ' . (string)($order['customer_last_name'] ?? ''));
        if ($customerName === '') $customerName = (string)$order['customer_name'];
        $lines = ["🛍️ NEW ORDER — Dukame", '', 'Store: '.$shopName, 'Order: '.$order['order_number'], '', 'Customer:', $customerName, '📞 '.$order['customer_phone'], '', 'Items:'];
        foreach (($order['items'] ?? []) as $item) {
            $optionText = '';
            $selected = json_decode($item['selected_options'] ?? '[]', true) ?: [];
            if ($selected) $optionText = ' [' . implode(', ', $selected) . ']';
            $lines[] = $item['product_name'].$optionText.' × '.$item['quantity'].' — '.$order['currency'].' '.number_format((float)$item['line_total'], 2);
        }
        $lines[]=''; $lines[]='Fulfilment: '.($order['fulfillment_method']==='pickup'?'Store pickup':'Delivery');
        if (($order['delivery_zone_name'] ?? '') !== '') $lines[]='Delivery area: '.$order['delivery_zone_name'];
        $lines[]=''; $lines[]='TOTAL: '.$order['currency'].' '.number_format((float)$order['total'],2); $lines[]=''; $lines[]='Track order: '.rtrim((string)($_ENV['APP_URL']??''),'/').'/track'; $lines[]=''; $lines[]='Manage this order: '.rtrim((string)($_ENV['APP_URL']??''),'/').'/orders/view?id='.$order['id'];
        return implode("\n", $lines);
    }
}
