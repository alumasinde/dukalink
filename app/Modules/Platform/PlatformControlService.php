<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Subscriptions\SubscriptionAdminService;
use PDO;
use RuntimeException;

final class PlatformControlService
{
    public function __construct(private PDO $db) {}

    public function setMerchantStatus(int $merchantId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new RuntimeException('Invalid merchant status.');
        }
        $q = $this->db->prepare("UPDATE users SET status=:status WHERE id=:id AND role='merchant'");
        $q->execute(['status' => $status, 'id' => $merchantId]);
        if ($q->rowCount() < 1) throw new RuntimeException('Merchant not found.');
        if ($status !== 'active') {
            $this->db->prepare("UPDATE shops SET status='suspended' WHERE owner_id=:id AND status='active'")
                ->execute(['id' => $merchantId]);
        }
    }

    public function setShopStatus(int $shopId, string $status): void
    {
        if (!in_array($status, ['draft', 'active', 'suspended', 'closed'], true)) {
            throw new RuntimeException('Invalid shop status.');
        }
        // The current schema predates CLOSED. Keep the control compatible with it.
        if ($status === 'closed') $status = 'suspended';
        $q = $this->db->prepare('UPDATE shops SET status=:status WHERE id=:id');
        $q->execute(['status' => $status, 'id' => $shopId]);
        if ($q->rowCount() < 1) throw new RuntimeException('Shop not found.');
    }

    public function activateSubscription(int $subscriptionId, int $months = 1): void
    {
        $service = new SubscriptionAdminService($this->db);
        $this->db->beginTransaction();
        try {
            $service->activate($subscriptionId, $months);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelSubscription(int $subscriptionId): void
    {
        (new SubscriptionAdminService($this->db))->cancel($subscriptionId);
    }
}
