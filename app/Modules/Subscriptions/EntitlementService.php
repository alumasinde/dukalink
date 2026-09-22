<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions;

use PDO;
use RuntimeException;

final class EntitlementService
{
    private SubscriptionPlanRepository $plans;
    private SubscriptionRepository $subscriptions;

    public function __construct(private PDO $db)
    {
        $this->plans=new SubscriptionPlanRepository($db);
        $this->subscriptions=new SubscriptionRepository($db);
    }

    public function subscription(int $shopId):?array { return $this->subscriptions->currentForShop($shopId); }

    public function plan(int $shopId):?array
    {
        $s=$this->subscription($shopId); return $s?$this->plans->find((int)$s['plan_id']):null;
    }

    public function isUsable(int $shopId):bool
    {
        $s=$this->subscription($shopId); if(!$s)return false;
        return in_array((string)$s['status'],['trial','active','past_due'],true);
    }

    public function feature(int $shopId,string $key):?array
    {
        $plan=$this->plan($shopId); if(!$plan)return null;
        foreach($plan['features'] as $feature) if((string)$feature['feature_key']===$key)return $feature;
        return null;
    }

    public function enabled(int $shopId,string $key):bool
    {
        if(!$this->isUsable($shopId))return false; $f=$this->feature($shopId,$key); return $f!==null&&(bool)$f['enabled'];
    }

    public function limit(int $shopId,string $key):?int
    {
        if(!$this->isUsable($shopId))return 0; $f=$this->feature($shopId,$key);
        if(!$f||!(bool)$f['enabled'])return 0; return $f['limit_value']===null?null:(int)$f['limit_value'];
    }

    public function usage(int $shopId,string $key):int
    {
        return match($key){
            'products.max'=>(int)$this->count('products',$shopId,"status <> 'archived'"),
            'categories.max'=>(int)$this->count('categories',$shopId,"status <> 'hidden'"),
            'staff.max'=>1,
            default=>0,
        };
    }

    private function count(string $table,int $shopId,string $extra):int
    {
        $allowed=['products','categories']; if(!in_array($table,$allowed,true))return 0;
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE shop_id=:shop_id AND {$extra}"); $stmt->execute(['shop_id'=>$shopId]); return (int)$stmt->fetchColumn();
    }

    public function assertCanCreate(int $shopId,string $key,string $label):void
    {
        if(!$this->isUsable($shopId))throw new RuntimeException('Your subscription is not active. Renew or choose an active plan before adding more '.strtolower($label).'.');
        $limit=$this->limit($shopId,$key);
        if($limit===0)throw new RuntimeException('Your current plan does not include '.strtolower($label).'.');
        if($limit!==null && $this->usage($shopId,$key)>=$limit)throw new RuntimeException('You have reached your plan limit of '.$limit.' '.strtolower($label).'. Upgrade your plan to add more.');
    }

    public function usageSummary(int $shopId):array
    {
        $out=[]; foreach(['products.max'=>'Products','categories.max'=>'Categories','staff.max'=>'Staff'] as $key=>$label){$limit=$this->limit($shopId,$key);$out[$key]=['label'=>$label,'usage'=>$this->usage($shopId,$key),'limit'=>$limit,'unlimited'=>$limit===null];} return $out;
    }
}
