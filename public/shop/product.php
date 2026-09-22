<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Bootstrap\App; use App\Database\Database; use App\Modules\Products\ProductRepository; use App\Modules\Shops\ShopRepository; use function App\Support\e; use function App\Support\shop_url; use function App\Support\base_url;
new App(); $slug=trim((string)($_GET['slug']??'')); $productSlug=trim((string)($_GET['product']??'')); $db=Database::connection();
$stmt=$db->prepare('SELECT id FROM shops WHERE slug=:slug AND status="active" LIMIT 1'); $stmt->execute(['slug'=>$slug]); $shopId=(int)($stmt->fetchColumn()?:0); if(!$shopId){http_response_code(404);exit('Shop not found');}
$shop=(new ShopRepository($db))->find($shopId); $stmt=$db->prepare('SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.shop_id=:shop AND p.slug=:slug AND p.status="active" LIMIT 1'); $stmt->execute(['shop'=>$shopId,'slug'=>$productSlug]); $product=$stmt->fetch(); if(!$product){http_response_code(404);exit('Product not found');}
$options=json_decode($product['options_json']??'[]',true)?:[]; $currency=$shop['currency']?:'KES';
$price=(float)$product['price']; $compareAt=(float)($product['compare_at_price'] ?? 0); $isOffer=$compareAt>$price;
$isOutOfStock=!empty($product['track_inventory'])&&(int)$product['stock_quantity']<1;
$productUrl=base_url(shop_url($slug).'/product/'.$product['slug']);
$productDescription=trim(strip_tags((string)($product['description'] ?? '')));
if($productDescription==='') $productDescription='Buy '.$product['name'].' from '.$shop['name'].' online.';
$productImage=!empty($product['image_path'])?base_url($product['image_path']):'';
$seo=[
 'title'=>$product['name'].' | '.$shop['name'],
 'description'=>mb_substr($productDescription,0,155),
 'canonical'=>$productUrl,
 'image'=>$productImage,
 'type'=>'product',
 'robots'=>'index,follow',
 'schema'=>[
   '@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'description'=>$productDescription,'url'=>$productUrl,
   'image'=>$productImage?[$productImage]:[], 'category'=>$product['category_name']?:'Product',
   'brand'=>['@type'=>'Brand','name'=>$shop['name']],
   'offers'=>['@type'=>'Offer','url'=>$productUrl,'priceCurrency'=>$currency,'price'=>number_format($price,2,'.',''),'availability'=>$isOutOfStock?'https://schema.org/OutOfStock':'https://schema.org/InStock','itemCondition'=>'https://schema.org/NewCondition','seller'=>['@type'=>'Organization','name'=>$shop['name']]],
 ],
];
ob_start(); ?>
<div class="customer-store customer-product-page"><header class="customer-store-header"><div class="customer-container"><div class="customer-store-top"><a href="<?=e(shop_url($slug))?>" class="customer-back"> <span>Back to shop</span></a><a class="customer-cart" href="/cart?shop=<?=e($slug)?>"><span class="cart-icon">🛒</span><span class="cart-label">Cart</span><b data-cart-count>0</b></a></div></div></header>
<main class="customer-container customer-product-detail">
<?php
$discountPercent=$isOffer&&$compareAt>0?(int)round((($compareAt-$price)/$compareAt)*100):0;
?>
<div class="product-detail-grid <?= $isOutOfStock ? 'product-is-sold-out' : '' ?>">
    <div class="product-detail-media">
        <?php if($product['image_path']):?><img src="<?=e($product['image_path'])?>" alt="<?=e($product['name'])?>"><?php else:?><span>◇</span><?php endif;?>
        <?php if($isOffer): ?><span class="product-detail-sale-badge"><?= $discountPercent ?>% OFF</span><?php endif; ?>
        <?php if($isOutOfStock): ?><span class="product-detail-stock-badge">Out of stock</span><?php endif; ?>
    </div>
    <div class="product-detail-copy">
        <div class="customer-product-category"><?=e($product['category_name']?:'Product')?></div>
        <h1><?=e($product['name'])?></h1>
        <div class="product-detail-price">
            <div class="customer-price-stack"><strong><?=e($currency)?> <?=number_format($price,2)?></strong><?php if($isOffer):?><del><?=e($currency)?> <?=number_format($compareAt,2)?></del><?php endif;?></div>
            <?php if($isOffer): ?><span class="product-offer-pill">Save <?=e($currency)?> <?=number_format($compareAt-$price,2)?> · <?= $discountPercent ?>% off</span><?php endif; ?>
        </div>
        <?php if(!empty($product['description'])):?><div class="product-description"><p data-description><?=nl2br(e($product['description']))?></p><button type="button" data-read-more>Read more</button></div><?php endif;?>

        <div class="product-quantity">
            <label>Quantity</label>
            <div class="quantity-control">
                <button type="button" data-qty-minus <?= $isOutOfStock ? 'disabled' : '' ?>>−</button>
                <input value="1" min="1" <?= !empty($product['track_inventory']) ? 'max="'.max(1,(int)$product['stock_quantity']).'"' : '' ?> inputmode="numeric" data-qty <?= $isOutOfStock ? 'disabled' : '' ?>>
                <button type="button" data-qty-plus <?= $isOutOfStock ? 'disabled' : '' ?>>+</button>
            </div>
            <?php if(!empty($product['track_inventory']) && !$isOutOfStock): ?><small class="product-stock-note"><?= (int)$product['stock_quantity'] ?> available</small><?php endif; ?>
        </div>

        <?php if($options):?><div class="product-option-group"><label>Choose size / option</label><div class="product-options" data-option-group><?php foreach($options as $option):?><button type="button" class="product-option" data-option value="<?=e($option)?>" <?= $isOutOfStock ? 'disabled' : '' ?>><?=e($option)?></button><?php endforeach;?></div></div><?php endif;?>

        <button class="customer-primary-action" type="button" data-detail-add data-product-id="<?= (int)$product['id']?>" data-product-name="<?=e($product['name'])?>" data-product-price="<?=e((string)$product['price'])?>" data-product-image="<?=e((string)$product['image_path'])?>" data-product-slug="<?=e($product['slug'])?>" data-product-stock="<?= (int)$product['stock_quantity']?>" data-track-inventory="<?= !empty($product['track_inventory']) ? '1' : '0' ?>" <?= $isOutOfStock ? 'disabled' : '' ?>><?= $isOutOfStock ? 'Out of stock' : 'Add to cart' ?></button>
        <p class="product-no-account">No account needed. Add items and order when you're ready.</p>
    </div>
</div></main><div class="customer-cart-toast" data-floating-cart hidden><div><small>Cart</small><strong><span data-cart-count>0</span> items · <span data-cart-total>0</span></strong></div><a href="/cart?shop=<?=e($slug)?>">View cart </a></div></div>
<script>window.DUKAME_STORE=<?=json_encode(['slug'=>$slug,'currency'=>$currency],JSON_UNESCAPED_SLASHES)?>; window.DUKAME_PRODUCT_OPTIONS=<?=json_encode($options,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>; window.DUKAME_PRODUCT_STOCK=<?=json_encode(['track'=>!empty($product['track_inventory']),'stock'=>(int)$product['stock_quantity']],JSON_UNESCAPED_SLASHES)?>;</script>
<?php $content=ob_get_clean();$title=$product['name'].' · '.$shop['name'];require dirname(__DIR__,2).'/app/Views/layouts/customer.php';
