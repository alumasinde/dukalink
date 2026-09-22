ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('merchant','admin') NOT NULL DEFAULT 'merchant' AFTER status;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    trial_days INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_subscription_plans_public (is_active,is_public,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plan_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    feature_name VARCHAR(150) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    limit_value BIGINT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_plan_feature (plan_id,feature_key),
    KEY idx_subscription_plan_features_plan (plan_id,sort_order),
    CONSTRAINT fk_subscription_plan_features_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('trial','active','past_due','expired','cancelled') NOT NULL DEFAULT 'trial',
    billing_interval ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
    starts_at DATETIME NOT NULL,
    trial_ends_at DATETIME NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    grace_ends_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscriptions_shop (shop_id),
    KEY idx_subscriptions_status (status),
    KEY idx_subscriptions_plan (plan_id),
    CONSTRAINT fk_subscriptions_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    shop_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(120) NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    billing_interval ENUM('monthly','annual') NOT NULL,
    status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    phone VARCHAR(20) NULL,
    period_start DATETIME NULL,
    period_end DATETIME NULL,
    paid_at DATETIME NULL,
    metadata TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_payment_provider_reference (provider,provider_reference),
    KEY idx_subscription_payments_shop (shop_id,created_at),
    KEY idx_subscription_payments_subscription (subscription_id),
    CONSTRAINT fk_subscription_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscription_payments_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order)
SELECT 'Basic','basic','For getting your first shop online.',499,4990,'KES',14,1,1,0,10
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE slug='basic');

INSERT INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order)
SELECT 'Growth','growth','For shops growing their catalogue and orders.',999,9990,'KES',14,1,1,1,20
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE slug='growth');

INSERT INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,trial_days,is_active,is_public,is_featured,sort_order)
SELECT 'Business','business','For established shops that need more room.',1999,19990,'KES',14,1,1,0,30
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE slug='business');

INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'products.max','Products',1,10,10 FROM subscription_plans p WHERE p.slug='basic'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='products.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'categories.max','Categories',1,5,20 FROM subscription_plans p WHERE p.slug='basic'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='categories.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'payments.mpesa','M-Pesa payments',1,NULL,30 FROM subscription_plans p WHERE p.slug='basic'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='payments.mpesa');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.whatsapp','WhatsApp orders',1,NULL,40 FROM subscription_plans p WHERE p.slug='basic'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.whatsapp');

INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'products.max','Products',1,100,10 FROM subscription_plans p WHERE p.slug='growth'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='products.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'categories.max','Categories',1,25,20 FROM subscription_plans p WHERE p.slug='growth'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='categories.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'payments.mpesa','M-Pesa payments',1,NULL,30 FROM subscription_plans p WHERE p.slug='growth'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='payments.mpesa');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.whatsapp','WhatsApp orders',1,NULL,40 FROM subscription_plans p WHERE p.slug='growth'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.whatsapp');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.analytics','Analytics',1,NULL,50 FROM subscription_plans p WHERE p.slug='growth'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.analytics');

INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'products.max','Products',1,NULL,10 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='products.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'categories.max','Categories',1,NULL,20 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='categories.max');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'payments.mpesa','M-Pesa payments',1,NULL,30 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='payments.mpesa');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.whatsapp','WhatsApp orders',1,NULL,40 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.whatsapp');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.analytics','Analytics',1,NULL,50 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.analytics');
INSERT INTO subscription_plan_features (plan_id,feature_key,feature_name,enabled,limit_value,sort_order)
SELECT id,'features.custom_domain','Custom domain',1,NULL,60 FROM subscription_plans p WHERE p.slug='business'
AND NOT EXISTS (SELECT 1 FROM subscription_plan_features f WHERE f.plan_id=p.id AND f.feature_key='features.custom_domain');

INSERT INTO subscriptions (shop_id,plan_id,status,billing_interval,starts_at,trial_ends_at,current_period_start,current_period_end,grace_ends_at)
SELECT sh.id,p.id,CASE WHEN p.trial_days>0 THEN 'trial' ELSE 'active' END,'monthly',CURRENT_TIMESTAMP,
       CASE WHEN p.trial_days>0 THEN DATE_ADD(CURRENT_TIMESTAMP,INTERVAL p.trial_days DAY) ELSE NULL END,
       CURRENT_TIMESTAMP,
       DATE_ADD(CURRENT_TIMESTAMP,INTERVAL p.trial_days DAY),
       DATE_ADD(DATE_ADD(CURRENT_TIMESTAMP,INTERVAL p.trial_days DAY),INTERVAL 3 DAY)
FROM shops sh CROSS JOIN subscription_plans p
LEFT JOIN subscriptions existing ON existing.shop_id=sh.id
WHERE p.slug='basic' AND p.is_active=1 AND existing.id IS NULL;
