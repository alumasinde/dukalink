ALTER TABLE shop_settings
    ADD COLUMN IF NOT EXISTS allow_delivery TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_cash_on_delivery,
    ADD COLUMN IF NOT EXISTS allow_store_pickup TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_delivery,
    ADD COLUMN IF NOT EXISTS allow_cash_on_pickup TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_store_pickup,
    ADD COLUMN IF NOT EXISTS delivery_pricing_mode ENUM('flat','zone') NOT NULL DEFAULT 'flat' AFTER allow_cash_on_pickup,
    ADD COLUMN IF NOT EXISTS delivery_flat_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER delivery_pricing_mode,
    ADD COLUMN IF NOT EXISTS free_delivery_minimum DECIMAL(12,2) NULL AFTER delivery_flat_fee,
    ADD COLUMN IF NOT EXISTS pickup_address VARCHAR(500) NULL AFTER free_delivery_minimum,
    ADD COLUMN IF NOT EXISTS pickup_instructions VARCHAR(1000) NULL AFTER pickup_address;

CREATE TABLE IF NOT EXISTS delivery_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_delivery_zones_shop_name (shop_id, name),
    KEY idx_delivery_zones_shop_status (shop_id, status, sort_order),
    CONSTRAINT fk_delivery_zones_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS fulfillment_method ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery' AFTER order_channel,
    ADD COLUMN IF NOT EXISTS delivery_zone_id BIGINT UNSIGNED NULL AFTER fulfillment_method,
    ADD COLUMN IF NOT EXISTS delivery_zone_name VARCHAR(120) NULL AFTER delivery_zone_id;

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('mpesa','cash_on_delivery','cash_on_pickup','whatsapp') NOT NULL DEFAULT 'mpesa';


-- Existing stores keep delivery disabled until the merchant explicitly configures it.
-- Store pickup is enabled by default because pickup does not require a delivery fee.
