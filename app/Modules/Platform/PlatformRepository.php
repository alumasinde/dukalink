<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use PDO;

final class PlatformRepository
{
    public function __construct(private PDO $db) {}

    public function metrics(): array
    {
        $row = $this->db->query("SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'merchant') AS merchants,
            (SELECT COUNT(*) FROM shops) AS shops,
            (SELECT COUNT(*) FROM shops WHERE status = 'active') AS active_shops,
            (SELECT COUNT(*) FROM shops WHERE status = 'draft') AS draft_shops,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'trial') AS trials,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') AS active_subscriptions,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'past_due') AS past_due_subscriptions,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'expired') AS expired_subscriptions,
            (SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'paid') AS subscription_revenue")->fetch() ?: [];

        return [
            'merchants' => (int)($row['merchants'] ?? 0),
            'shops' => (int)($row['shops'] ?? 0),
            'active_shops' => (int)($row['active_shops'] ?? 0),
            'draft_shops' => (int)($row['draft_shops'] ?? 0),
            'trials' => (int)($row['trials'] ?? 0),
            'active_subscriptions' => (int)($row['active_subscriptions'] ?? 0),
            'past_due_subscriptions' => (int)($row['past_due_subscriptions'] ?? 0),
            'expired_subscriptions' => (int)($row['expired_subscriptions'] ?? 0),
            'subscription_revenue' => (float)($row['subscription_revenue'] ?? 0),
        ];
    }

    public function recentMerchants(int $limit = 8): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->db->query("SELECT u.id, u.first_name, u.last_name, u.phone, u.email, u.status, u.created_at,
            sh.id AS shop_id, sh.name AS shop_name, sh.slug, sh.status AS shop_status,
            p.name AS plan_name, s.status AS subscription_status
            FROM users u
            LEFT JOIN shops sh ON sh.owner_id = u.id
            LEFT JOIN subscriptions s ON s.id = (
                SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = sh.id
                ORDER BY s2.id DESC LIMIT 1
            )
            LEFT JOIN subscription_plans p ON p.id = s.plan_id
            WHERE u.role = 'merchant'
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public function merchants(string $search = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(10, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        $where = ["u.role = 'merchant'"];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.first_name LIKE :search OR u.last_name LIKE :search OR u.phone LIKE :search OR u.email LIKE :search OR sh.name LIKE :search OR sh.slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN shops sh ON sh.owner_id = u.id WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT u.id, u.first_name, u.last_name, u.phone, u.email, u.status, u.created_at,
            sh.id AS shop_id, sh.name AS shop_name, sh.slug, sh.status AS shop_status,
            p.name AS plan_name, s.status AS subscription_status
            FROM users u
            LEFT JOIN shops sh ON sh.owner_id = u.id
            LEFT JOIN subscriptions s ON s.id = (
                SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = sh.id
                ORDER BY s2.id DESC LIMIT 1
            )
            LEFT JOIN subscription_plans p ON p.id = s.plan_id
            WHERE {$whereSql}
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int)ceil($total / $perPage))];
    }

    public function shops(string $search = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(10, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(sh.name LIKE :search OR sh.slug LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.phone LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['draft', 'active', 'suspended'], true)) {
            $where[] = 'sh.status = :status';
            $params['status'] = $status;
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM shops sh INNER JOIN users u ON u.id = sh.owner_id WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT sh.id, sh.name, sh.slug, sh.status, sh.created_at, sh.owner_id,
            u.first_name, u.last_name, u.phone,
            p.name AS plan_name, s.status AS subscription_status,
            (SELECT COUNT(*) FROM products pr WHERE pr.shop_id = sh.id) AS product_count,
            (SELECT COUNT(*) FROM orders o WHERE o.shop_id = sh.id) AS order_count
            FROM shops sh
            INNER JOIN users u ON u.id = sh.owner_id
            LEFT JOIN subscriptions s ON s.id = (
                SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = sh.id
                ORDER BY s2.id DESC LIMIT 1
            )
            LEFT JOIN subscription_plans p ON p.id = s.plan_id
            WHERE {$whereSql}
            ORDER BY sh.created_at DESC, sh.id DESC
            LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int)ceil($total / $perPage))];
    }

    public function orders(string $search = '', string $status = '', string $paymentStatus = '', int $page = 1, int $perPage = 20): array
    {
        $page=max(1,$page); $perPage=max(10,min(100,$perPage)); $offset=($page-1)*$perPage;
        $where=['1=1']; $params=[];
        if($search!=='') { $where[]='(o.order_number LIKE :search OR o.customer_name LIKE :search OR o.customer_phone LIKE :search OR sh.name LIKE :search OR sh.slug LIKE :search)'; $params['search']='%'.$search.'%'; }
        if(in_array($status,['pending','confirmed','preparing','ready','delivered','cancelled','rejected'],true)){ $where[]='o.status=:status'; $params['status']=$status; }
        if(in_array($paymentStatus,['pending','paid','failed','refunded'],true)){ $where[]='o.payment_status=:payment_status'; $params['payment_status']=$paymentStatus; }
        $w=implode(' AND ',$where);
        $c=$this->db->prepare("SELECT COUNT(*) FROM orders o INNER JOIN shops sh ON sh.id=o.shop_id WHERE {$w}"); foreach($params as $k=>$v)$c->bindValue(':'.$k,$v); $c->execute(); $total=(int)$c->fetchColumn();
        $q=$this->db->prepare("SELECT o.id,o.order_number,o.customer_name,o.customer_phone,o.currency,o.subtotal,o.delivery_fee,o.total,o.payment_method,o.payment_status,o.status,o.created_at,sh.id shop_id,sh.name shop_name,sh.slug FROM orders o INNER JOIN shops sh ON sh.id=o.shop_id WHERE {$w} ORDER BY o.created_at DESC,o.id DESC LIMIT :limit OFFSET :offset"); foreach($params as $k=>$v)$q->bindValue(':'.$k,$v); $q->bindValue(':limit',$perPage,\PDO::PARAM_INT); $q->bindValue(':offset',$offset,\PDO::PARAM_INT); $q->execute();
        return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    public function payments(string $search='', string $status='', string $provider='', int $page=1, int $perPage=20): array
    {
        $page=max(1,$page); $perPage=max(10,min(100,$perPage)); $offset=($page-1)*$perPage; $where=['1=1']; $params=[];
        if($search!==''){ $where[]='(p.provider_reference LIKE :search OR p.mpesa_receipt LIKE :search OR sh.name LIKE :search OR sh.slug LIKE :search OR u.phone LIKE :search)'; $params['search']='%'.$search.'%'; }
        if(in_array($status,['pending','paid','failed','cancelled','refunded'],true)){ $where[]='p.status=:status'; $params['status']=$status; }
        if(in_array($provider,['mpesa'],true)){ $where[]='p.provider=:provider'; $params['provider']=$provider; }
        $w=implode(' AND ',$where);
        $c=$this->db->prepare("SELECT COUNT(*) FROM subscription_payments p INNER JOIN shops sh ON sh.id=p.shop_id INNER JOIN users u ON u.id=sh.owner_id WHERE {$w}"); foreach($params as $k=>$v)$c->bindValue(':'.$k,$v); $c->execute(); $total=(int)$c->fetchColumn();
        $q=$this->db->prepare("SELECT p.id,p.subscription_id,p.target_plan_id,p.shop_id,p.provider,p.provider_reference,p.amount,p.currency,p.billing_interval,p.status,p.phone,p.mpesa_receipt,p.result_code,p.result_description,p.paid_at,p.created_at,sh.name shop_name,sh.slug,u.first_name,u.last_name,sp.name plan_name FROM subscription_payments p INNER JOIN shops sh ON sh.id=p.shop_id INNER JOIN users u ON u.id=sh.owner_id LEFT JOIN subscription_plans sp ON sp.id=p.target_plan_id WHERE {$w} ORDER BY p.created_at DESC,p.id DESC LIMIT :limit OFFSET :offset"); foreach($params as $k=>$v)$q->bindValue(':'.$k,$v); $q->bindValue(':limit',$perPage,\PDO::PARAM_INT); $q->bindValue(':offset',$offset,\PDO::PARAM_INT); $q->execute();
        return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    public function subscriptions(string $search='', string $status='', string $plan='', int $page=1, int $perPage=20): array
    {
        $page=max(1,$page); $perPage=max(10,min(100,$perPage)); $offset=($page-1)*$perPage; $where=['1=1']; $params=[];
        if($search!==''){ $where[]='(sh.name LIKE :search OR sh.slug LIKE :search OR u.phone LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)'; $params['search']='%'.$search.'%'; }
        if(in_array($status,['trial','active','past_due','expired','cancelled'],true)){ $where[]='s.status=:status'; $params['status']=$status; }
        if($plan!==''){ $where[]='sp.slug=:plan'; $params['plan']=$plan; }
        $w=implode(' AND ',$where);
        $c=$this->db->prepare("SELECT COUNT(*) FROM subscriptions s INNER JOIN shops sh ON sh.id=s.shop_id INNER JOIN users u ON u.id=sh.owner_id INNER JOIN subscription_plans sp ON sp.id=s.plan_id WHERE {$w}"); foreach($params as $k=>$v)$c->bindValue(':'.$k,$v); $c->execute(); $total=(int)$c->fetchColumn();
        $q=$this->db->prepare("SELECT s.id,s.shop_id,s.plan_id,s.status,s.billing_interval,s.starts_at,s.trial_ends_at,s.current_period_start,s.current_period_end,s.grace_ends_at,s.cancelled_at,s.created_at,sp.name plan_name,sp.slug plan_slug,sp.currency,sp.monthly_price,sp.annual_price,sh.name shop_name,sh.slug,u.first_name,u.last_name,u.phone FROM subscriptions s INNER JOIN shops sh ON sh.id=s.shop_id INNER JOIN users u ON u.id=sh.owner_id INNER JOIN subscription_plans sp ON sp.id=s.plan_id WHERE {$w} ORDER BY s.updated_at DESC,s.id DESC LIMIT :limit OFFSET :offset"); foreach($params as $k=>$v)$q->bindValue(':'.$k,$v); $q->bindValue(':limit',$perPage,\PDO::PARAM_INT); $q->bindValue(':offset',$offset,\PDO::PARAM_INT); $q->execute();
        return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    public function platformUsers(): array
    {
        return $this->db->query("SELECT id,first_name,last_name,phone,email,role,status,last_login_at,created_at FROM platform_users ORDER BY created_at DESC,id DESC")->fetchAll();
    }

    public function auditLogs(int $page=1,int $perPage=30): array
    {
        $page=max(1,$page); $perPage=max(10,min(100,$perPage)); $offset=($page-1)*$perPage;
        $total=(int)$this->db->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn();
        $q=$this->db->prepare("SELECT a.*,p.first_name,p.last_name,p.email,p.role FROM platform_audit_logs a LEFT JOIN platform_users p ON p.id=a.platform_user_id ORDER BY a.created_at DESC,a.id DESC LIMIT :limit OFFSET :offset"); $q->bindValue(':limit',$perPage,\PDO::PARAM_INT); $q->bindValue(':offset',$offset,\PDO::PARAM_INT); $q->execute();
        return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
    }

    public function merchantDetail(int $merchantId): ?array
    {
        if ($merchantId < 1) return null;
        $stmt = $this->db->prepare("SELECT
            u.id, u.first_name, u.last_name, u.phone, u.email, u.status, u.role, u.created_at, u.updated_at,
            sh.id AS shop_id, sh.name AS shop_name, sh.slug, sh.status AS shop_status, sh.business_type,
            sh.description AS shop_description, sh.phone AS shop_phone, sh.created_at AS shop_created_at,
            s.id AS subscription_id, s.status AS subscription_status, s.billing_interval,
            s.starts_at, s.trial_ends_at, s.current_period_start, s.current_period_end, s.grace_ends_at,
            sp.id AS plan_id, sp.name AS plan_name, sp.slug AS plan_slug, sp.monthly_price, sp.annual_price, sp.currency
            FROM users u
            LEFT JOIN shops sh ON sh.owner_id = u.id
            LEFT JOIN subscriptions s ON s.id = (
                SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = sh.id ORDER BY s2.id DESC LIMIT 1
            )
            LEFT JOIN subscription_plans sp ON sp.id = s.plan_id
            WHERE u.id = :id AND u.role = 'merchant'
            LIMIT 1");
        $stmt->execute(['id' => $merchantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function merchantOrders(int $merchantId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->db->prepare("SELECT o.id, o.order_number, o.customer_name, o.total, o.currency,
            o.payment_method, o.payment_status, o.status, o.created_at, sh.id AS shop_id, sh.name AS shop_name
            FROM orders o
            INNER JOIN shops sh ON sh.id = o.shop_id
            WHERE sh.owner_id = :merchant_id
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT {$limit}");
        $stmt->execute(['merchant_id' => $merchantId]);
        return $stmt->fetchAll();
    }

    public function merchantPaymentSummary(int $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT
            COUNT(*) AS payments,
            COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END),0) AS paid_amount,
            SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) AS pending_count
            FROM subscription_payments p
            INNER JOIN shops sh ON sh.id = p.shop_id
            WHERE sh.owner_id = :merchant_id");
        $stmt->execute(['merchant_id' => $merchantId]);
        return $stmt->fetch() ?: ['payments'=>0,'paid_amount'=>0,'paid_count'=>0,'pending_count'=>0];
    }

    public function shopDetail(int $shopId): ?array
    {
        if ($shopId < 1) return null;
        $stmt = $this->db->prepare("SELECT
            sh.id, sh.owner_id, sh.name, sh.slug, sh.business_type, sh.description, sh.phone, sh.status,
            sh.created_at, sh.updated_at,
            u.first_name, u.last_name, u.phone AS owner_phone, u.email AS owner_email, u.status AS owner_status,
            s.id AS subscription_id, s.status AS subscription_status, s.billing_interval,
            s.starts_at, s.trial_ends_at, s.current_period_start, s.current_period_end, s.grace_ends_at,
            sp.id AS plan_id, sp.name AS plan_name, sp.slug AS plan_slug, sp.monthly_price, sp.annual_price, sp.currency,
            ss.currency AS shop_currency, ss.mpesa_phone, ss.allow_cash_on_delivery, ss.whatsapp_number,
            (SELECT COUNT(*) FROM products pr WHERE pr.shop_id = sh.id) AS product_count,
            (SELECT COUNT(*) FROM products pr WHERE pr.shop_id = sh.id AND pr.status = 'active') AS active_product_count,
            (SELECT COUNT(*) FROM categories c WHERE c.shop_id = sh.id) AS category_count,
            (SELECT COUNT(*) FROM orders o WHERE o.shop_id = sh.id) AS order_count,
            (SELECT COUNT(*) FROM customers c WHERE c.shop_id = sh.id) AS customer_count,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.shop_id = sh.id AND o.payment_status = 'paid') AS paid_order_value
            FROM shops sh
            INNER JOIN users u ON u.id = sh.owner_id
            LEFT JOIN shop_settings ss ON ss.shop_id = sh.id
            LEFT JOIN subscriptions s ON s.id = (
                SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = sh.id ORDER BY s2.id DESC LIMIT 1
            )
            LEFT JOIN subscription_plans sp ON sp.id = s.plan_id
            WHERE sh.id = :id
            LIMIT 1");
        $stmt->execute(['id' => $shopId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function shopProducts(int $shopId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->db->prepare("SELECT id, name, slug, price, compare_at_price, stock_quantity, track_inventory, status, created_at
            FROM products WHERE shop_id = :shop_id ORDER BY created_at DESC, id DESC LIMIT {$limit}");
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }

    public function shopOrders(int $shopId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->db->prepare("SELECT id, order_number, customer_name, total, currency, payment_method,
            payment_status, status, created_at FROM orders WHERE shop_id = :shop_id
            ORDER BY created_at DESC, id DESC LIMIT {$limit}");
        $stmt->execute(['shop_id' => $shopId]);
        return $stmt->fetchAll();
    }

}
