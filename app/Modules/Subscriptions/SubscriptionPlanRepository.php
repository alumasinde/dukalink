<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions;

use PDO;

final class SubscriptionPlanRepository
{
    public function __construct(private PDO $db) {}

    public function publicPlans(): array
    {
        $stmt = $this->db->query("SELECT id, name, slug, description, monthly_price, annual_price, currency, trial_days, is_featured, sort_order FROM subscription_plans WHERE is_active=1 AND is_public=1 ORDER BY sort_order ASC, monthly_price ASC, id ASC");
        $plans = $stmt->fetchAll();
        foreach ($plans as &$plan) $plan['features'] = $this->features((int)$plan['id']);
        unset($plan);
        return $plans;
    }

    public function all(): array
    {
        return $this->db->query("SELECT id,name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order FROM subscription_plans ORDER BY sort_order ASC, monthly_price ASC, id ASC")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt=$this->db->prepare("SELECT id,name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order FROM subscription_plans WHERE id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]); $plan=$stmt->fetch();
        if (!$plan) return null;
        $plan['features']=$this->features($id); return $plan;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt=$this->db->prepare("SELECT id,name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order FROM subscription_plans WHERE slug=:slug LIMIT 1");
        $stmt->execute(['slug'=>$slug]); $plan=$stmt->fetch();
        if (!$plan) return null;
        $plan['features']=$this->features((int)$plan['id']); return $plan;
    }

    public function features(int $planId): array
    {
        $stmt=$this->db->prepare("SELECT id,feature_key,feature_name,enabled,limit_value,sort_order FROM subscription_plan_features WHERE plan_id=:plan_id ORDER BY sort_order ASC,id ASC");
        $stmt->execute(['plan_id'=>$planId]); return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt=$this->db->prepare("INSERT INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order) VALUES (:name,:slug,:description,:monthly_price,:annual_price,:currency,:trial_days,:is_active,:is_public,:is_featured,:sort_order)");
        $stmt->execute(['name'=>trim((string)$data['name'],' '),'slug'=>trim((string)$data['slug']),'description'=>trim((string)($data['description']??''))?:null,'monthly_price'=>max(0,(float)$data['monthly_price']),'annual_price'=>max(0,(float)$data['annual_price']),'currency'=>strtoupper(trim((string)($data['currency']??'KES')))?:'KES','trial_days'=>max(0,(int)($data['trial_days']??0)),'is_active'=>!empty($data['is_active'])?1:0,'is_public'=>!empty($data['is_public'])?1:0,'is_featured'=>!empty($data['is_featured'])?1:0,'sort_order'=>(int)($data['sort_order']??0)]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id,array $data):void
    {
        $stmt=$this->db->prepare("UPDATE subscription_plans SET name=:name,slug=:slug,description=:description,monthly_price=:monthly_price,annual_price=:annual_price,currency=:currency,trial_days=:trial_days,is_active=:is_active,is_public=:is_public,is_featured=:is_featured,sort_order=:sort_order WHERE id=:id");
        $stmt->execute(['id'=>$id,'name'=>trim((string)$data['name']),'slug'=>trim((string)$data['slug']),'description'=>trim((string)($data['description']??''))?:null,'monthly_price'=>max(0,(float)$data['monthly_price']),'annual_price'=>max(0,(float)$data['annual_price']),'currency'=>strtoupper(trim((string)($data['currency']??'KES')))?:'KES','trial_days'=>max(0,(int)($data['trial_days']??0)),'is_active'=>!empty($data['is_active'])?1:0,'is_public'=>!empty($data['is_public'])?1:0,'is_featured'=>!empty($data['is_featured'])?1:0,'sort_order'=>(int)($data['sort_order']??0)]);
    }

    public function saveFeature(int $planId,string $key,string $name,bool $enabled,?int $limit,int $sortOrder):void
    {
        $stmt=$this->db->prepare("INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order) VALUES (:plan_id,:feature_key,:feature_name,:enabled,:limit_value,:sort_order) ON DUPLICATE KEY UPDATE feature_name=VALUES(feature_name),enabled=VALUES(enabled),limit_value=VALUES(limit_value),sort_order=VALUES(sort_order)");
        $stmt->execute(['plan_id'=>$planId,'feature_key'=>trim($key),'feature_name'=>trim($name),'enabled'=>$enabled?1:0,'limit_value'=>$limit,'sort_order'=>$sortOrder]);
    }
}
