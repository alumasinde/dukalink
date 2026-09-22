ALTER TABLE shop_settings
    ADD COLUMN IF NOT EXISTS mpesa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mpesa_phone;

UPDATE shop_settings
SET mpesa_enabled = CASE
    WHEN mpesa_phone IS NOT NULL
         AND mpesa_consumer_key IS NOT NULL
         AND mpesa_consumer_secret IS NOT NULL
         AND mpesa_passkey IS NOT NULL
         AND currency = 'KES'
    THEN 1 ELSE 0
END;
