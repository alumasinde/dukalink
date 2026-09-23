# Dukame Styles

`styles/` is the canonical source for Dukame CSS.

- `variables.css` — design tokens and theme variables
- `app.css` — global, merchant and storefront layout styles
- `components.css` — reusable component styles
- `pages/` — page-specific styles

Browser-served copies live under `public/assets/css/`.

### Customer storefront

- `pages/customer.css` — public storefront, product detail, cart, checkout and order-tracking styles.

When changing customer-facing styles, edit `styles/pages/customer.css` first and mirror it to `public/assets/css/pages/customer.css` for browser delivery.
