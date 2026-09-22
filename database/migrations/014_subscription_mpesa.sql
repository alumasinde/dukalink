ALTER TABLE subscription_payments
    ADD COLUMN IF NOT EXISTS target_plan_id BIGINT UNSIGNED NULL AFTER subscription_id,
    ADD COLUMN IF NOT EXISTS merchant_request_id VARCHAR(120) NULL AFTER provider_reference,
    ADD COLUMN IF NOT EXISTS checkout_request_id VARCHAR(120) NULL AFTER merchant_request_id,
    ADD COLUMN IF NOT EXISTS result_code VARCHAR(20) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS result_description VARCHAR(255) NULL AFTER result_code,
    ADD COLUMN IF NOT EXISTS mpesa_receipt VARCHAR(80) NULL AFTER result_description,
    ADD COLUMN IF NOT EXISTS raw_callback LONGTEXT NULL AFTER metadata;
