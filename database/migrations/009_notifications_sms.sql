CREATE TABLE IF NOT EXISTS notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(50) NOT NULL,
    template TEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_template_shop_event (shop_id, event_key),
    CONSTRAINT fk_notification_template_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    event_key VARCHAR(50) NOT NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'sms',
    recipient VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    provider_message_id VARCHAR(100) NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    provider_code VARCHAR(30) NULL,
    provider_response TEXT NULL,
    error_message VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_order_event_channel (order_id, event_key, channel),
    KEY idx_notification_shop_created (shop_id, created_at),
    CONSTRAINT fk_notification_log_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_log_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_created', 'Dear {Customer First Name}, {Store Name} has received your order {Order Number} worth {Currency} {Order Total}. Track: {Track URL}'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_created'
WHERE nt.id IS NULL;
INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_confirmed', 'Dear {Customer First Name}, {Store Name} has confirmed order {Order Number}. We will keep you updated.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_confirmed'
WHERE nt.id IS NULL;
INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_preparing', 'Dear {Customer First Name}, {Store Name} is preparing order {Order Number}.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_preparing'
WHERE nt.id IS NULL;
INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_ready', 'Dear {Customer First Name}, your order {Order Number} from {Store Name} is ready for delivery or pickup. Track: {Track URL}'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_ready'
WHERE nt.id IS NULL;
INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_delivered', 'Dear {Customer First Name}, order {Order Number} from {Store Name} has been delivered. Thank you for shopping with us.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_delivered'
WHERE nt.id IS NULL;
INSERT INTO notification_templates (shop_id, event_key, template)
SELECT s.id, 'order_cancelled', 'Dear {Customer First Name}, order {Order Number} from {Store Name} has been cancelled. Please contact the store if you need help.'
FROM shops s LEFT JOIN notification_templates nt ON nt.shop_id=s.id AND nt.event_key='order_cancelled'
WHERE nt.id IS NULL;
