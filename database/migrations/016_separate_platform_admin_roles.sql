-- Platform administrators are stored exclusively in platform_users.
-- Any legacy users.role='admin' records are converted to merchant before the
-- merchant-user role is narrowed so the users table can never grant platform access.
UPDATE users SET role = 'merchant' WHERE role = 'admin';

ALTER TABLE users
    MODIFY COLUMN role ENUM('merchant') NOT NULL DEFAULT 'merchant';
