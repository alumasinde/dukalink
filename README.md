# Dukame — Phase 1

Vanilla PHP + MySQL foundation for a simple merchant commerce platform.

## Stack

- PHP 8.2+
- MySQL
- PDO
- Composer
- PSR-4 autoloading
- Bootstrap 5 via CDN
- Small custom CSS layer
- PHP sessions
- Dotenv

## Phase 1 includes

- Application bootstrap
- Environment configuration
- MySQL PDO connection
- PSR-4 namespaces/autoloading
- Session handling
- CSRF helper
- Flash messages
- Input validation
- Basic router
- Shared layouts/components
- Responsive Bootstrap UI
- Public landing page
- Login/register UI foundation
- Merchant onboarding wizard UI foundation
- Database migration for users, shops and shop settings

## Setup

1. Create a MySQL database:

```sql
CREATE DATABASE dukame CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Copy `.env.example` to `.env` and configure your database.

3. Install dependencies:

```bash
composer install
```

4. Generate autoload files:

```bash
composer dump-autoload
```

5. Run the migration:

```bash
php database/migrate.php
```

6. Start locally:

```bash
php -S localhost:8000 -t public
```

Then open:

http://localhost:8000

## Architecture

Request -> Controller -> Service -> Repository -> PDO/MySQL

Views are PHP templates. There is no framework.

## Phase 1 deliberately does NOT include

- M-Pesa STK
- SMS provider integration
- WhatsApp provider integration
- Products CRUD
- Orders
- Cart persistence
- Checkout
- Customer accounts

Those belong to later phases. The foundation is structured so they can be added without replacing the core architecture.


## Phase 1 UI note

The visual system uses configurable branding through `.env` (`APP_NAME`, `APP_TAGLINE`, `APP_PRIMARY_COLOR`, `APP_ACCENT_COLOR`). Bootstrap provides the base components, while the custom CSS is intentionally small and reusable. The sample landing content is presentation copy; merchant shops, products, carts and orders are designed to become database-driven in later phases.


## Phase 2 — Merchant catalogue + storefront

Phase 2 adds:

- Merchant dashboard
- Product management foundation
- Categories
- Product status (draft/active/archived)
- Featured products
- Pricing and compare-at pricing
- Basic inventory fields
- Database-driven public storefront
- Store categories
- Store search
- Mobile-first product grid
- Local browser cart foundation per shop
- Configurable brand/UI system retained from Phase 1

Run:

```bash
php database/migrate.php
```

The public storefront is intended to become the customer shopping experience. Checkout, order creation, M-Pesa STK, SMS and WhatsApp automation are deliberately reserved for the next phases.


## Clean URLs

The application uses clean URLs:

- `/login`
- `/register`
- `/onboarding`
- `/dashboard`
- `/products`
- `/products/create`
- `/products/edit?id=123`
- `/categories`
- `/settings/shop`
- `/{custom-shop-link}`

For local development, use:

```bash
php -S localhost:8000 -t public public/router.php
```

For XAMPP/Apache, `public/.htaccess` provides the same routing.

## Merchant shop links

A merchant can choose a custom public link during onboarding and later change it under:

`/settings/shop`

The slug is normalized, checked against reserved application routes, and checked for uniqueness before it is saved. Legacy `/shop/{slug}` links redirect to the shorter public URL.


## Merchant MVP
The merchant flow is now wired end-to-end: register, shop onboarding, login/logout, dashboard, categories, products (create/edit/delete), shop settings, payment defaults, and custom shop links. Use clean URLs such as `/login`, `/dashboard`, `/products`, `/categories`, and `/settings/shop`.

Product images support JPG, PNG and WebP up to 5MB and are stored under `public/uploads/products` for the MVP.


### Merchant settings
Merchants can upload a store logo and configure currency, M-Pesa phone, Cash on Delivery, and Daraja STK Push credentials from `/settings/shop`. Set a strong `APP_KEY` in `.env`; sensitive Daraja credentials are encrypted at rest.


### Merchant dashboard
The dashboard is intentionally lightweight: it shows shop status, catalogue counts, recent products, the public shop link, and a compact setup checklist. Orders and customers will appear when those modules are implemented.
