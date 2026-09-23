# Dukame production deployment

## Web root
Point Apache/Nginx **only** at `public/`. Do not expose the repository root.

## Apache
Copy `apache-vhost.conf.example`, replace the domain and certificate paths, enable rewrite/headers/SSL modules, and allow overrides for `public/`.

## Nginx
Copy `nginx.conf.example`, replace the domain/certificate paths and PHP-FPM socket for the installed PHP version.

## Environment
Create `.env` from `.env.example` and set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- a unique 32-byte base64 `APP_KEY`
- production `APP_URL` using HTTPS
- dedicated MySQL credentials
- TextSMS credentials if SMS is enabled
- production Daraja credentials where applicable
- a non-obvious `ADMIN_LINK`

Never commit `.env`.

## Release procedure

1. Put the application in maintenance mode at the load balancer/web-server level if your deployment supports it.
2. Deploy the new release to a new release directory.
3. Run `composer install --no-dev --classmap-authoritative`.
4. Run `php database/migrate.php`.
5. Run `php bin/qa.php`.
6. Point the web root/release symlink to the new release.
7. Verify `GET /health` returns `status=ok`.
8. Test merchant login, storefront, checkout, M-Pesa callback endpoint and platform login.
9. Resume traffic.

## Background notifications
Run `php bin/process-notifications.php` from cron/worker according to your hosting environment. SMS failures should be retried without blocking order creation.

## Backups
Back up MySQL and `public/uploads/` before releases. Test restoration periodically.

## HTTPS
M-Pesa callbacks require a publicly reachable HTTPS URL. Keep HSTS enabled only after HTTPS is permanent.
