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


## API v1

Dukame now has a versioned JSON API at `/api/v1`. The existing clean web URLs remain unchanged.

### Authentication

```text
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
```

Login returns a Bearer token. Send it on protected requests:

```text
Authorization: Bearer <token>
```

### Merchant API

```text
GET  /api/v1/shop
PUT  /api/v1/shop
PUT  /api/v1/shop/slug
PUT  /api/v1/shop/settings

GET    /api/v1/products
POST   /api/v1/products
GET    /api/v1/products/{id}
PUT    /api/v1/products/{id}
DELETE /api/v1/products/{id}

GET    /api/v1/categories
POST   /api/v1/categories
PUT    /api/v1/categories/{id}
DELETE /api/v1/categories/{id}
```

### Public Store API

```text
GET /api/v1/stores/{slug}
```

For example:

```text
GET /api/v1/stores/jazafurnitures
```

The API uses a standard response envelope:

```json
{
  "success": true,
  "data": {}
}
```

Errors use:

```json
{
  "success": false,
  "error": {
    "message": "Authentication required.",
    "code": "unauthenticated"
  }
}
```

API authentication uses opaque, hashed bearer tokens stored in `api_tokens`; plaintext tokens are only returned at login.

## API v1

Dukame exposes a versioned JSON API under `/api/v1`. The public storefront URLs remain clean (for example `/jazafurnitures`), while application clients use the API contract.

Core endpoints currently include:

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET|PUT /api/v1/shop`
- `PUT /api/v1/shop/slug`
- `PUT /api/v1/shop/settings`
- `GET|POST /api/v1/products`
- `GET|PUT|DELETE /api/v1/products/{id}`
- `GET|POST /api/v1/categories`
- `PUT|DELETE /api/v1/categories/{id}`
- `GET /api/v1/stores/{slug}`

Protected endpoints use `Authorization: Bearer <token>`.

## Merchant Orders & Customers

The merchant application now includes:

- `/orders` — order list with status filters and summary counts.
- `/orders/view?id=...` — order details and status updates.
- `/customers` — customers created from orders.
- `/customers/view?id=...` — customer details and order history.

Shop settings now include:

- Order number prefix (for example `DK`).
- Next order number (for example `1001`, producing `DK-01001`).
- WhatsApp number used for customer order links.

Migration `006_create_orders_customers.sql` creates customers, orders and order items and adds these shop settings.

### API v1 commerce endpoints

- `POST /api/v1/orders` — public order creation for a store.
- `POST /api/v1/orders/track` — public order tracking using order number + phone; no customer login.
- `GET /api/v1/orders` — authenticated merchant orders.
- `GET /api/v1/orders/{id}` — authenticated merchant order details.
- `PUT /api/v1/orders/{id}/status` — authenticated merchant status update.
- `GET /api/v1/customers` — authenticated merchant customers.
- `GET /api/v1/customers/{id}` — authenticated merchant customer details.

## Customer storefront

The public storefront is mobile-first and uses the versioned API for commerce actions. Customers do not need accounts.

- `/{shop-slug}` — public store
- `/{shop-slug}/product/{product-slug}` — product details
- `/cart` — cart for the active shop
- `/checkout` — guest checkout
- `/track` — order tracking by order number + phone

Adding to cart never redirects the customer away from the product list. The cart badge/floating cart updates immediately. Products may define optional choices such as `Small, Medium, Large, XL`; these are selected on the product page and stored with the order.

Orders are created through `POST /api/v1/orders`. When the shop has a WhatsApp number configured, the customer can open a pre-filled WhatsApp order message. Dukame does not require WhatsApp/Meta API access for this flow.


## Store publishing

New shops start as `draft`. A merchant can publish from the dashboard or Shop Settings once the minimum selling setup is complete:

- Shop details (description and phone)
- At least one active product
- WhatsApp number

Logo, categories, and M-Pesa configuration are optional for publishing. Publishing changes the shop to `active`, which makes the public root-level storefront available at `/{slug}`. Merchants can unpublish later, returning the shop to `draft`.

API v1 also exposes authenticated `POST /api/v1/shop/publish` and `POST /api/v1/shop/unpublish`.


## Customer checkout
- Primary action: Place order directly in Dukame.
- Alternative: Order on WhatsApp, using the same Dukame order number.
- Customer details use First Name, Last Name, Phone, optional Email, delivery location and notes.
- Orders are created server-side from trusted product prices; browser cart prices are never trusted.

## Subscription system

Dukame subscriptions are database-driven. Plan names, prices, trial days, public visibility, featured status and feature limits are stored in `subscription_plans` and `subscription_plan_features` rather than hardcoded in product logic or the landing page.

Run the normal migration command after updating the project:

```bash
php database/migrate.php
```

The subscription migration creates default Basic, Growth and Business plans and gives existing shops a Basic trial if they do not already have a subscription. New shops receive the configured Basic trial during onboarding.

### Admin access

Subscription plan management is restricted to users whose `users.role` is `admin`. Registration never accepts or assigns this role. For the first administrator, promote an existing trusted account directly in MySQL:

```sql
UPDATE users SET role = 'admin' WHERE phone = '2547XXXXXXXX';
```

Then log in normally and open `/admin/plans`.

Do not expose this SQL in the public application or accept a role value from merchant registration/API requests.

### Entitlement enforcement

Product and category creation is enforced server-side through `App\Modules\Subscriptions\EntitlementService`. UI limits are informational only; API requests receive a `subscription_limit` error when a merchant reaches a configured limit. Existing products are never deleted automatically after a downgrade or expiry.

### Subscription payment foundation

`subscription_payments` stores billing payment records separately from customer order payments. Actual subscription collection/renewal can be connected to M-Pesa without mixing subscription payments with order payments.


## Subscription roles and billing

- A normal registration creates a `merchant` user during shop onboarding. Merchants manage their own shop subscription.
- `admin` is reserved for the Dukame platform administrator. It is never assigned through public registration and cannot be used as a merchant session.
- Subscription plan configuration is controlled by platform admins; merchant plan changes require a verified payment.
- Subscription M-Pesa credentials are platform-level environment variables (`SUBSCRIPTION_MPESA_*`) and are separate from merchant shop M-Pesa credentials.
- A subscription plan changes only after the M-Pesa callback reports `ResultCode = 0`.
- Never put platform subscription credentials in a public repository or merchant-editable settings.
