<?php

declare(strict_types=1);

namespace App\Modules\Products;

use PDO;

final class ProductRepository
{
    public function __construct(private PDO $db) {}

    public function allForShop(int $shopId, ?string $status = null): array
    {
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.shop_id = :shop_id';

        $params = ['shop_id' => $shopId];

        if ($status !== null) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $shopId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.shop_id = :shop_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $shopId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products
             (shop_id, category_id, name, slug, description, options_json, sku, price, compare_at_price,
              stock_quantity, track_inventory, image_path, status, featured)
             VALUES
             (:shop_id, :category_id, :name, :slug, :description, :options_json, :sku, :price, :compare_at_price,
              :stock_quantity, :track_inventory, :image_path, :status, :featured)'
        );

        $stmt->execute([
            'shop_id' => $shopId,
            'category_id' => $data['category_id'] ?: null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'options_json' => $data['options_json'] ?? null,
            'sku' => $data['sku'] ?: null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?: null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'track_inventory' => !empty($data['track_inventory']) ? 1 : 0,
            'image_path' => $data['image_path'] ?: null,
            'status' => $data['status'] ?? 'draft',
            'featured' => !empty($data['featured']) ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $shopId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE products SET
                category_id = :category_id,
                name = :name,
                slug = :slug,
                description = :description,
                options_json = :options_json,
                sku = :sku,
                price = :price,
                compare_at_price = :compare_at_price,
                stock_quantity = :stock_quantity,
                track_inventory = :track_inventory,
                image_path = :image_path,
                status = :status,
                featured = :featured
             WHERE id = :id AND shop_id = :shop_id'
        );

        $stmt->execute([
            'id' => $id,
            'shop_id' => $shopId,
            'category_id' => $data['category_id'] ?: null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'options_json' => $data['options_json'] ?? null,
            'sku' => $data['sku'] ?: null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?: null,
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'track_inventory' => !empty($data['track_inventory']) ? 1 : 0,
            'image_path' => $data['image_path'] ?: null,
            'status' => $data['status'] ?? 'draft',
            'featured' => !empty($data['featured']) ? 1 : 0,
        ]);
    }

    public function delete(int $id, int $shopId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM products WHERE id = :id AND shop_id = :shop_id'
        );
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
    }
}
