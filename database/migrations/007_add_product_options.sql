ALTER TABLE products
    ADD COLUMN IF NOT EXISTS options_json TEXT NULL AFTER description;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS selected_options TEXT NULL AFTER product_name;
