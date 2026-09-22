ALTER TABLE shops
    ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL AFTER phone;

ALTER TABLE shop_settings
    ADD COLUMN IF NOT EXISTS mpesa_environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox' AFTER mpesa_phone,
    ADD COLUMN IF NOT EXISTS mpesa_shortcode VARCHAR(30) NULL AFTER mpesa_environment,
    ADD COLUMN IF NOT EXISTS mpesa_consumer_key TEXT NULL AFTER mpesa_shortcode,
    ADD COLUMN IF NOT EXISTS mpesa_consumer_secret TEXT NULL AFTER mpesa_consumer_key,
    ADD COLUMN IF NOT EXISTS mpesa_passkey TEXT NULL AFTER mpesa_consumer_secret;
