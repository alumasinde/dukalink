<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions;

use PDO;
use RuntimeException;

final class SubscriptionAdminService
{
    public function __construct(private PDO $db) {}

    public function activate(int $subscriptionId, int $months = 1): void
    {
        if ($subscriptionId < 1) throw new RuntimeException('Invalid subscription.');
        $months = max(1, min(24, $months));
        $stmt = $this->db->prepare('SELECT id, plan_id FROM subscriptions WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $subscriptionId]);
        if (!$stmt->fetch()) throw new RuntimeException('Subscription not found.');

        $q = $this->db->prepare("UPDATE subscriptions
            SET status='active', current_period_start=CURRENT_TIMESTAMP,
                current_period_end=DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$months} MONTH),
                grace_ends_at=DATE_ADD(DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$months} MONTH), INTERVAL 3 DAY),
                trial_ends_at=NULL, cancelled_at=NULL, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id");
        $q->execute(['id' => $subscriptionId]);
    }

    public function cancel(int $subscriptionId): void
    {
        $stmt = $this->db->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute(['id' => $subscriptionId]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Subscription not found.');
    }

    public function changePlan(int $subscriptionId, int $planId): void
    {
        $plan = $this->db->prepare('SELECT id, is_active FROM subscription_plans WHERE id=:id LIMIT 1');
        $plan->execute(['id' => $planId]);
        $row = $plan->fetch();
        if (!$row || !(int)$row['is_active']) throw new RuntimeException('The selected plan is not active.');

        $stmt = $this->db->prepare('UPDATE subscriptions SET plan_id=:plan_id, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['plan_id' => $planId, 'id' => $subscriptionId]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Subscription not found.');
    }
}
