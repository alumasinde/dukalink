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
