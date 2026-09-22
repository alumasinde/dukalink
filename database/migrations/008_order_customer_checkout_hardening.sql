ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS customer_first_name VARCHAR(100) NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS customer_last_name VARCHAR(100) NULL AFTER customer_first_name,
    ADD COLUMN IF NOT EXISTS order_channel ENUM('web','whatsapp') NOT NULL DEFAULT 'web' AFTER payment_method;

UPDATE orders
SET
    customer_first_name = TRIM(SUBSTRING_INDEX(customer_name, ' ', 1)),
    customer_last_name = NULLIF(TRIM(SUBSTRING(customer_name, LENGTH(SUBSTRING_INDEX(customer_name, ' ', 1)) + 1)), '')
WHERE customer_first_name IS NULL AND customer_name IS NOT NULL;
