<?php

declare(strict_types=1);

namespace App\Modules\Categories;

use PDO;

final class CategoryRepository
{
    public function __construct(private PDO $db) {}

    public function allForShop(int $shopId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM categories WHERE shop_id = :shop_id';
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }

    public function create(int $shopId, string $name, string $slug): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (shop_id, name, slug) VALUES (:shop_id, :name, :slug)'
        );
        $stmt->execute([
            'shop_id' => $shopId,
            'name' => $name,
            'slug' => $slug,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findForShop(int $id, int $shopId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM categories WHERE id = :id AND shop_id = :shop_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: null;
    }


    public function update(int $id, int $shopId, string $name, string $slug): void
    {
        $stmt = $this->db->prepare(
            'UPDATE categories SET name = :name, slug = :slug
             WHERE id = :id AND shop_id = :shop_id'
        );
        $stmt->execute(['id' => $id, 'shop_id' => $shopId, 'name' => $name, 'slug' => $slug]);
    }

    public function delete(int $id, int $shopId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM categories WHERE id = :id AND shop_id = :shop_id'
        );
        $stmt->execute(['id' => $id, 'shop_id' => $shopId]);
    }
}
