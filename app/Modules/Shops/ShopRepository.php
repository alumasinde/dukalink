<?php

declare(strict_types=1);

namespace App\Modules\Shops;

use PDO;
use function App\Support\decrypt_secret;
use function App\Support\encrypt_secret;

final class ShopRepository
{
    public function __construct(private PDO $db) {}

    public function find(int $shopId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, ss.currency, ss.order_number_prefix, ss.next_order_number, ss.whatsapp_number, ss.mpesa_phone, ss.allow_cash_on_delivery,
                    ss.mpesa_environment, ss.mpesa_shortcode, ss.mpesa_consumer_key,
                    ss.mpesa_consumer_secret, ss.mpesa_passkey
             FROM shops s
             LEFT JOIN shop_settings ss ON ss.shop_id = s.id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $shopId]);
        $shop = $stmt->fetch();
        if (!$shop) {
            return null;
        }

        $shop['mpesa_credentials_configured'] = !empty($shop['mpesa_consumer_key']) && !empty($shop['mpesa_consumer_secret']) && !empty($shop['mpesa_passkey']);
        unset($shop['mpesa_consumer_key'], $shop['mpesa_consumer_secret'], $shop['mpesa_passkey']);

        return $shop;
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


    public function mpesaCredentials(int $shopId): array
    {
        $stmt = $this->db->prepare('SELECT mpesa_environment, mpesa_shortcode, mpesa_consumer_key, mpesa_consumer_secret, mpesa_passkey FROM shop_settings WHERE shop_id = :shop_id LIMIT 1');
        $stmt->execute(['shop_id' => $shopId]);
        $row = $stmt->fetch() ?: [];

        return [
            'environment' => $row['mpesa_environment'] ?? 'sandbox',
            'shortcode' => $row['mpesa_shortcode'] ?? null,
            'consumer_key' => decrypt_secret($row['mpesa_consumer_key'] ?? null),
            'consumer_secret' => decrypt_secret($row['mpesa_consumer_secret'] ?? null),
            'passkey' => decrypt_secret($row['mpesa_passkey'] ?? null),
        ];
    }

    public function updateSettings(int $shopId, array $data): void
    {
        $current = $this->db->prepare('SELECT mpesa_consumer_key, mpesa_consumer_secret, mpesa_passkey FROM shop_settings WHERE shop_id = :shop_id LIMIT 1');
        $current->execute(['shop_id' => $shopId]);
        $existing = $current->fetch() ?: [];

        $consumerKey = trim((string)($data['mpesa_consumer_key'] ?? '')) !== ''
            ? encrypt_secret($data['mpesa_consumer_key']) : ($existing['mpesa_consumer_key'] ?? null);
        $consumerSecret = trim((string)($data['mpesa_consumer_secret'] ?? '')) !== ''
            ? encrypt_secret($data['mpesa_consumer_secret']) : ($existing['mpesa_consumer_secret'] ?? null);
        $passkey = trim((string)($data['mpesa_passkey'] ?? '')) !== ''
            ? encrypt_secret($data['mpesa_passkey']) : ($existing['mpesa_passkey'] ?? null);

        $stmt = $this->db->prepare(
            'UPDATE shop_settings
             SET currency = :currency,
                 order_number_prefix = :order_number_prefix,
                 next_order_number = :next_order_number,
                 whatsapp_number = :whatsapp_number,
                 mpesa_phone = :mpesa_phone,
                 mpesa_environment = :mpesa_environment,
                 mpesa_shortcode = :mpesa_shortcode,
                 mpesa_consumer_key = :mpesa_consumer_key,
                 mpesa_consumer_secret = :mpesa_consumer_secret,
                 mpesa_passkey = :mpesa_passkey,
                 allow_cash_on_delivery = :allow_cash_on_delivery
             WHERE shop_id = :shop_id'
        );
        $stmt->execute([
            'shop_id' => $shopId,
            'currency' => $data['currency'],
            'mpesa_phone' => $data['mpesa_phone'] ?: null,
            'whatsapp_number' => $data['whatsapp_number'] ?: null,
            'order_number_prefix' => strtoupper(trim((string)($data['order_number_prefix'] ?? 'DK'))),
            'next_order_number' => max(1, (int)($data['next_order_number'] ?? 1001)),
            'mpesa_environment' => $data['mpesa_environment'] ?? 'sandbox',
            'mpesa_shortcode' => $data['mpesa_shortcode'] ?: null,
            'mpesa_consumer_key' => $consumerKey,
            'mpesa_consumer_secret' => $consumerSecret,
            'mpesa_passkey' => $passkey,
            'allow_cash_on_delivery' => !empty($data['allow_cash_on_delivery']) ? 1 : 0,
        ]);
    }

    public function reserveOrderNumber(int $shopId): string
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT order_number_prefix, next_order_number FROM shop_settings WHERE shop_id = :shop_id FOR UPDATE');
            $stmt->execute(['shop_id' => $shopId]);
            $settings = $stmt->fetch();
            if (!$settings) {
                throw new \RuntimeException('Shop settings not found.');
            }

            $prefix = strtoupper(trim((string)($settings['order_number_prefix'] ?? 'DK')));
            $next = max(1, (int)($settings['next_order_number'] ?? 1001));
            $number = $prefix . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);

            $update = $this->db->prepare('UPDATE shop_settings SET next_order_number = :next_order_number WHERE shop_id = :shop_id');
            $update->execute(['next_order_number' => $next + 1, 'shop_id' => $shopId]);
            $this->db->commit();
            return $number;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function updateLogo(int $shopId, ?string $logoPath): void
    {
        $stmt = $this->db->prepare('UPDATE shops SET logo_path = :logo_path WHERE id = :id');
        $stmt->execute(['id' => $shopId, 'logo_path' => $logoPath]);
    }

    public function publish(int $shopId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shops SET status = 'active' WHERE id = :id AND status <> 'suspended'"
        );
        $stmt->execute(['id' => $shopId]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('This shop cannot be published.');
        }
    }

    public function unpublish(int $shopId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shops SET status = 'draft' WHERE id = :id AND status = 'active'"
        );
        $stmt->execute(['id' => $shopId]);
    }

}
