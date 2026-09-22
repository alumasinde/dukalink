<?php

declare(strict_types=1);

namespace App\Modules\Shops;

use PDO;

final class ShopRepository
{
    public function __construct(private PDO $db) {}

    public function find(int $shopId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, ss.currency, ss.mpesa_phone, ss.allow_cash_on_delivery
             FROM shops s
             LEFT JOIN shop_settings ss ON ss.shop_id = s.id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $shopId]);
        return $stmt->fetch() ?: null;
    }

    public function update(int $shopId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shops SET name = :name, business_type = :business_type,
             description = :description, phone = :phone
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $shopId,
            'name' => $data['name'],
            'business_type' => $data['business_type'] ?: null,
            'description' => $data['description'] ?: null,
            'phone' => $data['phone'] ?: null,
        ]);
    }

    public function slugExists(string $slug, ?int $exceptShopId = null): bool
    {
        $sql = 'SELECT id FROM shops WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptShopId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptShopId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function updateSlug(int $shopId, string $slug): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shops SET slug = :slug WHERE id = :id'
        );

        $stmt->execute([
            'id' => $shopId,
            'slug' => $slug,
        ]);
    }

    public function publish(int $shopId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shops SET status = 'active' WHERE id = :id"
        );

        $stmt->execute(['id' => $shopId]);
    }

}
