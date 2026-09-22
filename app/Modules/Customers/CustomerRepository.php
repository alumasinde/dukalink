<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $db) {}

    public function allForShop(int $shopId): array
    {
        $stmt = $this->db->prepare('SELECT c.*, COUNT(o.id) AS live_order_count
            FROM customers c
            LEFT JOIN orders o ON o.customer_id = c.id
            WHERE c.shop_id = :shop_id
            GROUP BY c.id
            ORDER BY COALESCE(c.last_order_at, c.created_at) DESC');
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }

    public function findForShop(int $id, int $shopId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = :id AND shop_id = :shop_id LIMIT 1');
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: null;
    }

    public function ordersForCustomer(int $customerId, int $shopId): array
    {
        $stmt = $this->db->prepare('SELECT id, order_number, total, currency, status, payment_status, created_at
            FROM orders WHERE customer_id = :customer_id AND shop_id = :shop_id ORDER BY created_at DESC');
        $stmt->execute(['customer_id' => $customerId, 'shop_id' => $shopId]);
        return $stmt->fetchAll();
    }
}
