<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use PDO;

final class OrderRepository
{
    public function __construct(private PDO $db) {}

    public function allForShop(int $shopId, ?string $status = null): array
    {
        $sql = 'SELECT o.*, c.id AS customer_record_id, c.total_orders AS customer_total_orders
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.shop_id = :shop_id';
        $params = ['shop_id' => $shopId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND o.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY o.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findForShop(int $id, int $shopId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id AND shop_id = :shop_id LIMIT 1');
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
        $order = $stmt->fetch();
        if (!$order) return null;
        $items = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $items->execute(['order_id' => $id]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    public function updateStatus(int $id, int $shopId, string $status): bool
    {
        $allowed = ['pending','confirmed','preparing','ready','delivered','cancelled','rejected'];
        if (!in_array($status, $allowed, true)) return false;
        $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id AND shop_id = :shop_id');
        $stmt->execute(['status' => $status, 'id' => $id, 'shop_id' => $shopId]);
        return $stmt->rowCount() > 0;
    }

    public function countByStatus(int $shopId): array
    {
        $stmt = $this->db->prepare('SELECT status, COUNT(*) AS total FROM orders WHERE shop_id = :shop_id GROUP BY status');
        $stmt->execute(['shop_id' => $shopId]);
        $result = array_fill_keys(['pending','confirmed','preparing','ready','delivered','cancelled','rejected'], 0);
        foreach ($stmt->fetchAll() as $row) $result[$row['status']] = (int)$row['total'];
        return $result;
    }
}
