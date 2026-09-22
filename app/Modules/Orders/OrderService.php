<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Shops\ShopRepository;
use PDO;
use function App\Support\normalize_phone;

final class OrderService
{
    public function __construct(private PDO $db) {}

    public function create(int $shopId, array $data): array
    {
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);
        $phone = normalize_phone((string)($data['customer_phone'] ?? ''));
        $email = trim((string)($data['customer_email'] ?? ''));
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($firstName === '' || $lastName === '' || !$items) throw new \InvalidArgumentException('First name, last name and at least one item are required.');
        if (mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) throw new \InvalidArgumentException('Names must be 100 characters or fewer.');
        if ($phone === '' || !preg_match('/^254\d{9}$/', $phone)) throw new \InvalidArgumentException('Enter a valid Kenyan phone number.');
        if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new \InvalidArgumentException('Enter a valid email address or leave it blank.');

        $shopRepo = new ShopRepository($this->db);
        $shop = $shopRepo->find($shopId);
        if (!$shop) throw new \RuntimeException('Shop not found.');

        $fulfillment = ($data['fulfillment_method'] ?? '') === 'pickup' ? 'pickup' : (($data['fulfillment_method'] ?? '') === 'delivery' ? 'delivery' : '');
        $allowDelivery = !empty($shop['allow_delivery']);
        $allowPickup = !empty($shop['allow_store_pickup']);
        if ($fulfillment === '' || ($fulfillment === 'delivery' && !$allowDelivery) || ($fulfillment === 'pickup' && !$allowPickup)) {
            throw new \InvalidArgumentException('Please choose an available delivery or pickup option.');
        }

        $deliveryAddress = trim((string)($data['delivery_address'] ?? ''));
        if ($fulfillment === 'delivery' && $deliveryAddress === '') throw new \InvalidArgumentException('Enter your delivery location or address.');
        if ($fulfillment === 'pickup') $deliveryAddress = '';

        $this->db->beginTransaction();
        try {
            $customer = $this->findOrCreateCustomer($shopId, $firstName, $lastName, $phone, $email);
            $orderNumber = $this->reserveOrderNumberInTransaction($shopId);
            $subtotal = 0.0;
            $resolvedItems = [];
            $productStmt = $this->db->prepare('SELECT id, name, sku, price, status, stock_quantity, track_inventory, options_json FROM products WHERE id = :id AND shop_id = :shop_id LIMIT 1');
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $productStmt->execute(['id' => $productId, 'shop_id' => $shopId]);
                $product = $productStmt->fetch();
                if (!$product || $product['status'] !== 'active') throw new \InvalidArgumentException('One of the selected products is no longer available.');
                if (!empty($product['track_inventory']) && $quantity > (int)$product['stock_quantity']) throw new \InvalidArgumentException('One of the selected products does not have enough stock.');
                $allowedOptions = json_decode($product['options_json'] ?? '[]', true) ?: [];
                $selectedOptions = is_array($item['options'] ?? null) ? array_values(array_filter(array_map('trim', $item['options']))) : [];
                if ($allowedOptions && (count($selectedOptions) !== 1 || !in_array($selectedOptions[0], $allowedOptions, true))) throw new \InvalidArgumentException('Please choose a valid option for one of the selected products.');
                if (!$allowedOptions && $selectedOptions) throw new \InvalidArgumentException('An option was supplied for a product that has no options.');
                $unitPrice = (float)$product['price'];
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;
                $resolvedItems[] = [$product, $quantity, $lineTotal, $selectedOptions];
            }

            $deliveryFee = 0.0;
            $deliveryZoneId = null;
            $deliveryZoneName = null;
            if ($fulfillment === 'delivery') {
                $mode = ($shop['delivery_pricing_mode'] ?? 'flat') === 'zone' ? 'zone' : 'flat';
                if ($mode === 'zone') {
                    $deliveryZoneId = (int)($data['delivery_zone_id'] ?? 0);
                    $zone = $deliveryZoneId > 0 ? $shopRepo->deliveryZone($shopId, $deliveryZoneId) : null;
                    if (!$zone || $zone['status'] !== 'active') throw new \InvalidArgumentException('Please choose a valid delivery area.');
                    $deliveryFee = (float)$zone['fee'];
                    $deliveryZoneName = $zone['name'];
                } else {
                    $deliveryFee = max(0, (float)($shop['delivery_flat_fee'] ?? 0));
                }
                $freeMinimum = $shop['free_delivery_minimum'] !== null ? (float)$shop['free_delivery_minimum'] : null;
                if ($freeMinimum !== null && $freeMinimum > 0 && $subtotal >= $freeMinimum) $deliveryFee = 0.0;
            }

            $total = $subtotal + $deliveryFee;
            $orderChannel = in_array(($data['order_channel'] ?? 'web'), ['web', 'whatsapp'], true) ? $data['order_channel'] : 'web';
            $paymentMethod = (string)($data['payment_method'] ?? '');
            $mpesaEnabled = !empty($shop['mpesa_enabled']) && !empty($shop['mpesa_credentials_configured']) && !empty($shop['mpesa_phone']) && (($shop['currency'] ?? 'KES') === 'KES');
            $codEnabled = $fulfillment === 'delivery' && !empty($shop['allow_cash_on_delivery']);
            $cashPickupEnabled = $fulfillment === 'pickup' && !empty($shop['allow_cash_on_pickup']);
            if ($paymentMethod === 'mpesa' && !$mpesaEnabled) throw new \InvalidArgumentException('M-Pesa is not enabled for this store. Please choose another payment method.');
            if ($paymentMethod === 'cash_on_delivery' && !$codEnabled) throw new \InvalidArgumentException('Cash on delivery is not available for this order.');
            if ($paymentMethod === 'cash_on_pickup' && !$cashPickupEnabled) throw new \InvalidArgumentException('Pay at pickup is not available for this store.');
            if (!in_array($paymentMethod, ['cash_on_delivery','cash_on_pickup','mpesa'], true)) throw new \InvalidArgumentException('Please choose an available payment method.');

            $stmt = $this->db->prepare('INSERT INTO orders
                (shop_id, customer_id, customer_first_name, customer_last_name, order_number, customer_name, customer_phone, customer_email, delivery_address, notes, currency, subtotal, delivery_fee, total, payment_method, order_channel, fulfillment_method, delivery_zone_id, delivery_zone_name, status)
                VALUES (:shop_id, :customer_id, :first_name, :last_name, :order_number, :customer_name, :customer_phone, :customer_email, :delivery_address, :notes, :currency, :subtotal, :delivery_fee, :total, :payment_method, :order_channel, :fulfillment_method, :delivery_zone_id, :delivery_zone_name, "pending")');
            $stmt->execute([
                'shop_id'=>$shopId,'customer_id'=>$customer['id'],'first_name'=>$firstName,'last_name'=>$lastName,'order_number'=>$orderNumber,'customer_name'=>$name,
                'customer_phone'=>$phone,'customer_email'=>$email ?: null,'delivery_address'=>$deliveryAddress ?: null,'notes'=>trim((string)($data['notes'] ?? '')) ?: null,
                'currency'=>$shop['currency'] ?? 'KES','subtotal'=>$subtotal,'delivery_fee'=>$deliveryFee,'total'=>$total,'payment_method'=>$paymentMethod,
                'order_channel'=>$orderChannel,'fulfillment_method'=>$fulfillment,'delivery_zone_id'=>$deliveryZoneId,'delivery_zone_name'=>$deliveryZoneName,
            ]);
            $orderId=(int)$this->db->lastInsertId();
            $itemStmt=$this->db->prepare('INSERT INTO order_items (order_id, product_id, product_name, selected_options, sku, quantity, unit_price, line_total) VALUES (:order_id,:product_id,:product_name,:selected_options,:sku,:quantity,:unit_price,:line_total)');
            foreach ($resolvedItems as [$product,$quantity,$lineTotal,$selectedOptions]) {
                $itemStmt->execute(['order_id'=>$orderId,'product_id'=>$product['id'],'product_name'=>$product['name'],'selected_options'=>$selectedOptions?json_encode($selectedOptions,JSON_UNESCAPED_UNICODE):null,'sku'=>$product['sku']?:null,'quantity'=>$quantity,'unit_price'=>$product['price'],'line_total'=>$lineTotal]);
            }
            $this->refreshCustomerStats((int)$customer['id'],$shopId);
            $this->db->commit();
            return (new OrderRepository($this->db))->findForShop($orderId,$shopId) ?? [];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function findOrCreateCustomer(int $shopId, string $firstName, string $lastName, string $phone, ?string $email): array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE shop_id = :shop_id AND phone = :phone LIMIT 1');
        $stmt->execute(['shop_id' => $shopId, 'phone' => $phone]);
        $existing = $stmt->fetch();
        if ($existing) {
            $update = $this->db->prepare('UPDATE customers SET first_name = :first_name, last_name = :last_name, email = :email WHERE id = :id AND shop_id = :shop_id');
            $update->execute(['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email ?: ($existing['email'] ?? null),'id'=>$existing['id'],'shop_id'=>$shopId]);
            return $existing;
        }
        $insert = $this->db->prepare('INSERT INTO customers (shop_id, first_name, last_name, phone, email) VALUES (:shop_id,:first_name,:last_name,:phone,:email)');
        $insert->execute(['shop_id'=>$shopId,'first_name'=>$firstName,'last_name'=>$lastName,'phone'=>$phone,'email'=>$email ?: null]);
        $id = (int)$this->db->lastInsertId();
        return ['id'=>$id,'phone'=>$phone];
    }

    private function refreshCustomerStats(int $customerId, int $shopId): void
    {
        $stmt = $this->db->prepare('UPDATE customers c SET total_orders = (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.shop_id = :shop_count), total_spent = (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.customer_id = c.id AND o.shop_id = :shop_sum), last_order_at = (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = c.id AND o.shop_id = :shop_last) WHERE c.id = :customer_id AND c.shop_id = :shop_customer');
        $stmt->execute(['shop_count'=>$shopId,'shop_sum'=>$shopId,'shop_last'=>$shopId,'customer_id'=>$customerId,'shop_customer'=>$shopId]);
    }

    private function reserveOrderNumberInTransaction(int $shopId): string
    {
        $stmt = $this->db->prepare('SELECT order_number_prefix, next_order_number FROM shop_settings WHERE shop_id = :shop_id FOR UPDATE');
        $stmt->execute(['shop_id'=>$shopId]);
        $settings = $stmt->fetch();
        if (!$settings) throw new \RuntimeException('Shop settings not found.');
        $prefix = strtoupper(trim((string)($settings['order_number_prefix'] ?? 'DK')));
        $next = max(1, (int)($settings['next_order_number'] ?? 1001));
        $number = $prefix . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
        $update = $this->db->prepare('UPDATE shop_settings SET next_order_number = :next WHERE shop_id = :shop_id');
        $update->execute(['next'=>$next+1,'shop_id'=>$shopId]);
        return $number;
    }

}
