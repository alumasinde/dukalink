<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Products\ProductRepository;
use App\Modules\Shops\ShopRepository;
use function App\Support\e;
use function App\Support\shop_url; use function App\Support\base_url;
new App();
$slug = trim((string)($_GET['slug'] ?? ''));
$db = Database::connection();
$stmt = $db->prepare('SELECT id FROM shops WHERE slug = :slug AND status = "active" LIMIT 1');
$stmt->execute(['slug' => $slug]);
$shopId = (int)($stmt->fetchColumn() ?: 0);
if (!$shopId) { http_response_code(404); echo 'Shop not found'; exit; }
$shop = (new ShopRepository($db))->find($shopId);
$products = (new ProductRepository($db))->allForShop($shopId, 'active');
$categories = (new CategoryRepository($db))->allForShop($shopId, true);
$selectedCategory = (int)($_GET['category'] ?? 0);
if ($selectedCategory) $products = array_values(array_filter($products, fn($p) => (int)$p['category_id'] === $selectedCategory));
$currency = $shop['currency'] ?: 'KES';
$storeUrl = base_url(shop_url($slug));
$storeDescription = trim(strip_tags((string)($shop['description'] ?? '')));
if ($storeDescription === '') {
    $storeDescription = trim(($shop['business_type'] ?: 'Online shop') . ' from ' . $shop['name'] . '. Shop products online and place your order with Dukame.');
}
$storeImage = !empty($shop['logo_path']) ? base_url($shop['logo_path']) : '';
$seoSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Store',
    'name' => $shop['name'],
    'url' => $storeUrl,
    'description' => $storeDescription,
    'currenciesAccepted' => $currency,
    'makesOffer' => array_map(static function (array $p) use ($currency, $storeUrl) {
        return [
            '@type' => 'Offer',
            'url' => $storeUrl . '/product/' . $p['slug'],
            'priceCurrency' => $currency,
            'price' => number_format((float)$p['price'], 2, '.', ''),
            'availability' => (!empty($p['track_inventory']) && (int)$p['stock_quantity'] < 1) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            'itemOffered' => ['@type' => 'Product', 'name' => $p['name']],
        ];
    }, array_slice($products, 0, 20)),
];
$seo = [
    'title' => $shop['name'] . ' | Shop Online',
    'description' => mb_substr($storeDescription, 0, 155),
    'canonical' => $selectedCategory ? $storeUrl : $storeUrl,
    'image' => $storeImage,
    'robots' => $selectedCategory ? 'noindex,follow' : 'index,follow',
    'schema' => $seoSchema,
];
ob_start(); ?>
<div class="customer-store" data-store-page>
    <header class="customer-store-header">
        <div class="customer-container">
            <div class="customer-store-top">
                <a href="<?= e(shop_url($slug)) ?>" class="customer-brand">
                    <span class="customer-logo">
                        <?php if (!empty($shop['logo_path'])): ?><img src="<?= e($shop['logo_path']) ?>" alt="<?= e($shop['name']) ?>"><?php else: ?><span><?= e(strtoupper(substr($shop['name'], 0, 1))) ?></span><?php endif; ?>
                    </span>
                    <span class="customer-brand-copy"><strong><?= e($shop['name']) ?></strong><small><?= e($shop['business_type'] ?: 'Online shop') ?></small></span>
                </a>
                <a class="customer-cart" href="/cart?shop=<?= e($slug) ?>" aria-label="View cart"><span class="cart-icon">🛒</span><span class="cart-label">Cart</span><b data-cart-count>0</b></a>
            </div>
            <?php if (!empty($shop['description'])): ?><p class="customer-store-description"><?= e($shop['description']) ?></p><?php endif; ?>
            <div class="customer-search"><span>⌕</span><input type="search" placeholder="Search products..." data-store-search aria-label="Search products"></div>
            <?php if ($categories): ?><div class="customer-category-scroller"><a href="<?= e(shop_url($slug)) ?>" class="customer-chip <?= !$selectedCategory ? 'active' : '' ?>">All</a><?php foreach($categories as $category): ?><a href="<?= e(shop_url($slug)) ?>?category=<?= (int)$category['id'] ?>" class="customer-chip <?= $selectedCategory === (int)$category['id'] ? 'active' : '' ?>"><?= e($category['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
        </div>
    </header>

    <main class="customer-container customer-main">
        <div class="customer-section-heading"><div><span class="customer-eyebrow"><?= $selectedCategory ? 'COLLECTION' : 'WELCOME' ?></span><h1><?= e($selectedCategory ? 'Shop collection' : 'Explore our products') ?></h1></div><span class="customer-count"><?= count($products) ?> items</span></div>
        <?php if (!$products): ?><div class="customer-empty"><div>◇</div><h2>No products yet</h2><p>This store hasn't added products to this collection.</p></div>
        <?php else: ?>
        <div class="customer-grid">
        <?php foreach($products as $product):
            $options = json_decode($product['options_json'] ?? '[]', true) ?: [];
            $hasOptions = count($options) > 0;
            $compareAt = (float)($product['compare_at_price'] ?? 0);
            $price = (float)$product['price'];
            $isOffer = $compareAt > $price;
            $discountPercent = $isOffer && $compareAt > 0 ? (int)round((($compareAt - $price) / $compareAt) * 100) : 0;
            $isOutOfStock = !empty($product['track_inventory']) && (int)$product['stock_quantity'] < 1;
        ?>
            <article class="customer-product-card store-product <?= $isOutOfStock ? 'is-sold-out' : '' ?>" data-name="<?= e(strtolower($product['name'])) ?>">
                <a class="customer-product-media" href="<?= e(shop_url($slug)) ?>/product/<?= e($product['slug']) ?>">
                    <?php if ($product['image_path']): ?><img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" loading="lazy"><?php else: ?><span>◇</span><?php endif; ?>
                    <?php if ($product['featured']): ?><span class="customer-badge">Featured</span><?php endif; ?>
                    <?php if ($isOffer): ?><span class="customer-sale-badge"><?= $discountPercent ?>% OFF</span><?php endif; ?>
                    <?php if ($isOutOfStock): ?><span class="customer-stock-badge">Out of stock</span><?php endif; ?>
                </a>
                <div class="customer-product-body">
                    <div class="customer-product-category"><?= e($product['category_name'] ?: 'Product') ?></div>
                    <a href="<?= e(shop_url($slug)) ?>/product/<?= e($product['slug']) ?>" class="customer-product-title"><?= e($product['name']) ?></a>
                    <?php if (!empty($product['description'])): ?><p class="customer-product-description"><?= e($product['description']) ?></p><?php endif; ?>
                    <?php if ($hasOptions): ?><div class="customer-product-options">Available: <?= e(implode(' · ', $options)) ?></div><?php endif; ?>
                    <?php if ($isOffer): ?><div class="customer-offer-note">Save <?= e($currency) ?> <?= number_format($compareAt - $price, 2) ?></div><?php endif; ?>
                    <?php if ($hasOptions): ?><div class="store-card-options" data-card-option-group>
                        <?php foreach ($options as $option): ?><button type="button" class="store-card-option" data-card-option value="<?= e($option) ?>"><?= e($option) ?></button><?php endforeach; ?>
                    </div><?php endif; ?>
                    <div class="customer-product-bottom">
                        <div class="customer-price-stack"><strong><?= e($currency) ?> <?= number_format($price, 2) ?></strong><?php if ($isOffer): ?><del><?= e($currency) ?> <?= number_format($compareAt, 2) ?></del><?php endif; ?></div>
                        <?php if ($isOutOfStock): ?><button class="customer-add-button is-disabled" type="button" disabled>Sold out</button>
                        <?php else: ?><div class="store-card-quantity" data-card data-product-id="<?= (int)$product['id'] ?>" data-product-name="<?= e($product['name']) ?>" data-product-price="<?= e((string)$product['price']) ?>" data-product-image="<?= e((string)$product['image_path']) ?>" data-product-slug="<?= e($product['slug']) ?>" data-product-stock="<?= (int)$product['stock_quantity'] ?>" data-track-inventory="<?= !empty($product['track_inventory']) ? '1' : '0' ?>" data-has-options="<?= $hasOptions ? '1' : '0' ?>">
                            <button type="button" data-card-minus aria-label="Decrease quantity">−</button><span data-card-qty>0</span><button type="button" data-card-plus aria-label="Increase quantity">+</button>
                        </div><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?></div><?php endif; ?>
    </main>
    <div class="customer-cart-toast" data-floating-cart hidden><div><small>Cart</small><strong><span data-cart-count>0</span> items · <span data-cart-total>0</span></strong></div><a href="/cart?shop=<?= e($slug) ?>">View cart</a></div>
</div>
<script>window.DUKAME_STORE=<?= json_encode(['slug'=>$slug,'currency'=>$currency,'payment_methods'=>['mpesa'=>!empty($shop['mpesa_enabled']) && !empty($shop['mpesa_credentials_configured']) && !empty($shop['mpesa_phone']) && $currency==='KES','cash_on_delivery'=>!empty($shop['allow_cash_on_delivery'])]], JSON_UNESCAPED_SLASHES) ?>;</script>
<?php $content=ob_get_clean(); $title=$shop['name']; require dirname(__DIR__,2).'/app/Views/layouts/customer.php';
