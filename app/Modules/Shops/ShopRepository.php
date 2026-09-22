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
                    ss.mpesa_environment, ss.mpesa_shortcode, ss.mpesa_enabled, ss.mpesa_consumer_key,
                    ss.mpesa_consumer_secret, ss.mpesa_passkey,
                    ss.allow_delivery, ss.allow_store_pickup, ss.allow_cash_on_pickup, ss.delivery_pricing_mode,
                    ss.delivery_flat_fee, ss.free_delivery_minimum, ss.pickup_address, ss.pickup_instructions
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
                 mpesa_enabled = :mpesa_enabled,
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
            'mpesa_enabled' => !empty($data['mpesa_enabled']) ? 1 : 0,
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

    public function deliveryZones(int $shopId, bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, fee, sort_order, status FROM delivery_zones WHERE shop_id = :shop_id';
        if ($activeOnly) $sql .= " AND status = 'active'";
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }

    public function deliveryZone(int $shopId, int $zoneId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, fee, sort_order, status FROM delivery_zones WHERE id = :id AND shop_id = :shop_id LIMIT 1");
        $stmt->execute(['id' => $zoneId, 'shop_id' => $shopId]);
        return $stmt->fetch() ?: null;
    }

    public function saveDeliverySettings(int $shopId, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE shop_settings SET
            allow_delivery = :allow_delivery,
            allow_store_pickup = :allow_store_pickup,
            allow_cash_on_pickup = :allow_cash_on_pickup,
            delivery_pricing_mode = :delivery_pricing_mode,
            delivery_flat_fee = :delivery_flat_fee,
            free_delivery_minimum = :free_delivery_minimum,
            pickup_address = :pickup_address,
            pickup_instructions = :pickup_instructions
            WHERE shop_id = :shop_id');
        $stmt->execute([
            'shop_id' => $shopId,
            'allow_delivery' => !empty($data['allow_delivery']) ? 1 : 0,
            'allow_store_pickup' => !empty($data['allow_store_pickup']) ? 1 : 0,
            'allow_cash_on_pickup' => !empty($data['allow_cash_on_pickup']) ? 1 : 0,
            'delivery_pricing_mode' => ($data['delivery_pricing_mode'] ?? 'flat') === 'zone' ? 'zone' : 'flat',
            'delivery_flat_fee' => max(0, (float)($data['delivery_flat_fee'] ?? 0)),
            'free_delivery_minimum' => (($data['free_delivery_minimum'] ?? '') !== '' && (float)$data['free_delivery_minimum'] > 0) ? (float)$data['free_delivery_minimum'] : null,
            'pickup_address' => trim((string)($data['pickup_address'] ?? '')) ?: null,
            'pickup_instructions' => trim((string)($data['pickup_instructions'] ?? '')) ?: null,
        ]);
    }

    public function createDeliveryZone(int $shopId, string $name, float $fee, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare('INSERT INTO delivery_zones (shop_id, name, fee, sort_order, status) VALUES (:shop_id, :name, :fee, :sort_order, \'active\')');
        $stmt->execute(['shop_id'=>$shopId, 'name'=>$name, 'fee'=>max(0,$fee), 'sort_order'=>$sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function updateDeliveryZone(int $shopId, int $zoneId, string $name, float $fee, int $sortOrder, string $status = 'active'): bool
    {
        $status = $status === 'hidden' ? 'hidden' : 'active';
        $stmt = $this->db->prepare('UPDATE delivery_zones SET name=:name, fee=:fee, sort_order=:sort_order, status=:status WHERE id=:id AND shop_id=:shop_id');
        $stmt->execute(['name'=>$name, 'fee'=>max(0,$fee), 'sort_order'=>$sortOrder, 'status'=>$status, 'id'=>$zoneId, 'shop_id'=>$shopId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteDeliveryZone(int $shopId, int $zoneId): void
    {
        $stmt = $this->db->prepare('DELETE FROM delivery_zones WHERE id=:id AND shop_id=:shop_id');
        $stmt->execute(['id'=>$zoneId, 'shop_id'=>$shopId]);
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
