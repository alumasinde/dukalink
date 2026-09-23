# Dukame Final QA Checklist

## Merchant
- Register
- Login/logout
- Onboarding
- Create/edit/delete product
- Product image upload
- Category create/edit/delete
- Shop settings and slug validation
- Publish/unpublish shop
- Delivery/pickup settings
- M-Pesa settings
- SMS templates
- Subscription plan selection
- Subscription payment

## Customer
- Open active merchant store
- Search products
- Filter category
- Product details
- Options/variants
- Add/remove/increment cart
- Refresh browser and retain cart
- Checkout validation
- Delivery/pickup pricing
- M-Pesa payment
- Cash payment where enabled
- WhatsApp alternative
- Order tracking
- Failed M-Pesa retry

## Platform
- Platform login/logout
- Dashboard
- Merchant list/detail
- Shop list/detail
- Orders
- Payments
- Plans
- Subscriptions
- Platform users/roles
- Audit log

## Security
- Production APP_KEY
- APP_DEBUG=false
- HTTPS
- CSRF on state-changing web actions
- Rate limits
- Upload execution blocked
- Public web root is `public/`
- `.env` and source artifacts are not public
- Database uses a dedicated least-privilege account

## Responsive/accessibility
- 360px mobile
- 768px tablet
- Desktop
- Keyboard navigation
- Visible focus state
- Skip-to-content link
- Reduced-motion preference
- Form labels/errors
- Touch targets

## Release verification
- `composer install --no-dev --classmap-authoritative`
- `php bin/qa.php`
- `php database/migrate.php`
- `GET /health`
- Check server/PHP logs after smoke test
