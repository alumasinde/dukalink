<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions;

use PDO;

final class SubscriptionRepository
{
    public function __construct(private PDO $db) {}

    public function currentForShop(int $shopId): ?array
    {
        $stmt=$this->db->prepare("SELECT s.id,s.shop_id,s.plan_id,s.status,s.billing_interval,s.starts_at,s.trial_ends_at,s.current_period_start,s.current_period_end,s.grace_ends_at,s.cancelled_at,p.name AS plan_name,p.slug AS plan_slug,p.description AS plan_description,p.monthly_price,p.annual_price,p.currency,p.trial_days FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.shop_id=:shop_id ORDER BY CASE s.status WHEN 'active' THEN 1 WHEN 'trial' THEN 2 WHEN 'past_due' THEN 3 WHEN 'expired' THEN 4 WHEN 'cancelled' THEN 5 ELSE 6 END,s.id DESC LIMIT 1");
        $stmt->execute(['shop_id'=>$shopId]); $row=$stmt->fetch();
        if (!$row) return null;
        $this->normalizeStatus($row);
        if (in_array($row['status'],['trial','active','past_due'],true)) return $row;
        $fresh=$this->db->prepare("SELECT s.id,s.shop_id,s.plan_id,s.status,s.billing_interval,s.starts_at,s.trial_ends_at,s.current_period_start,s.current_period_end,s.grace_ends_at,s.cancelled_at,p.name AS plan_name,p.slug AS plan_slug,p.description AS plan_description,p.monthly_price,p.annual_price,p.currency,p.trial_days FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.id=:id LIMIT 1");
        $fresh->execute(['id'=>$row['id']]); return $fresh->fetch() ?: $row;
    }

    private function normalizeStatus(array $row): void
    {
        $now=time(); $status=(string)$row['status'];
        if ($status==='trial' && !empty($row['trial_ends_at']) && strtotime((string)$row['trial_ends_at'])<$now) {
            $this->db->prepare("UPDATE subscriptions SET status='expired',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='trial'")->execute(['id'=>$row['id']]); return;
        }
        if ($status==='active' && !empty($row['current_period_end']) && strtotime((string)$row['current_period_end'])<$now) {
            $newStatus=!empty($row['grace_ends_at']) && strtotime((string)$row['grace_ends_at'])>=$now?'past_due':'expired';
            $this->db->prepare("UPDATE subscriptions SET status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='active'")->execute(['status'=>$newStatus,'id'=>$row['id']]); return;
        }
        if ($status==='past_due' && !empty($row['grace_ends_at']) && strtotime((string)$row['grace_ends_at'])<$now) {
            $this->db->prepare("UPDATE subscriptions SET status='expired',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='past_due'")->execute(['id'=>$row['id']]);
        }
    }

    public function createTrial(int $shopId,int $planId,int $trialDays):int
    {
        $now=new \DateTimeImmutable('now'); $end=$trialDays>0?$now->modify('+'.$trialDays.' days'):$now;
        $stmt=$this->db->prepare("INSERT INTO subscriptions (shop_id,plan_id,status,billing_interval,starts_at,trial_ends_at,current_period_start,current_period_end,grace_ends_at) VALUES (:shop_id,:plan_id,:status,'monthly',:starts_at,:trial_ends_at,:period_start,:period_end,:grace_ends_at)");
        $stmt->execute(['shop_id'=>$shopId,'plan_id'=>$planId,'status'=>$trialDays>0?'trial':'active','starts_at'=>$now->format('Y-m-d H:i:s'),'trial_ends_at'=>$trialDays>0?$end->format('Y-m-d H:i:s'):null,'period_start'=>$now->format('Y-m-d H:i:s'),'period_end'=>$end->format('Y-m-d H:i:s'),'grace_ends_at'=>$end->modify('+3 days')->format('Y-m-d H:i:s')]);
        return (int)$this->db->lastInsertId();
    }

    public function allForAdmin():array
    {
        return $this->db->query("SELECT s.id,s.shop_id,s.plan_id,s.status,s.billing_interval,s.starts_at,s.trial_ends_at,s.current_period_end,p.name AS plan_name,p.currency,p.monthly_price,p.annual_price,sh.name AS shop_name,sh.slug,u.phone AS owner_phone FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id INNER JOIN shops sh ON sh.id=s.shop_id INNER JOIN users u ON u.id=sh.owner_id ORDER BY s.created_at DESC")->fetchAll();
    }


}
