<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Products\ProductRepository;
use App\Modules\Shops\ShopRepository;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\Auth;
use function App\Support\e;
use function App\Support\store_product_image;
new App();
$db=Database::connection();
$merchant=Auth::requireMerchant($db);
$shopId=(int)$merchant['id'];
$id=(int)($_GET['id']??0);
if(!$shopId||!$id){header('Location: /products');exit;}
$repo=new ProductRepository($db);
$product=$repo->find($id,$shopId);
if(!$product){http_response_code(404);exit('Product not found');}
$categories=(new CategoryRepository($db))->allForShop($shopId);
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null)){Session::flash('error','Your form session expired.');}
    else{
        $name=trim((string)($_POST['name']??''));
        $price=(float)($_POST['price']??0);
        if($name===''||$price<0){Session::flash('error','Product name and a valid price are required.');}
        else{
            $imagePath = $product['image_path'];
            $newImage = store_product_image($_FILES['image'] ?? [], $shopId);
            if ($newImage !== null) {
                $imagePath = $newImage;
            }
            $repo->update($id,$shopId,[
                'category_id'=>$_POST['category_id']??null,'name'=>$name,'slug'=>$product['slug'],
                'description'=>trim((string)($_POST['description']??'')),
                'options_json'=>json_encode(array_values(array_filter(array_map('trim', explode(',', (string)($_POST['options'] ?? ''))))), JSON_UNESCAPED_UNICODE),
                'sku'=>trim((string)($_POST['sku']??'')),'price'=>$price,
                'compare_at_price'=>$_POST['compare_at_price']??null,
                'stock_quantity'=>(int)($_POST['stock_quantity']??0),
                'track_inventory'=>isset($_POST['track_inventory']),'image_path'=>$imagePath,
                'status'=>$_POST['status']??'draft','featured'=>isset($_POST['featured'])
            ]);
            Session::flash('success','Product updated.');
            header('Location: /products'); exit;
        }
    }
}
$merchantShop = (new ShopRepository($db))->find($shopId);
$merchantSection = 'products';
ob_start(); ?>
<div class="merchant-shell">
<?php require $root . '/app/Views/components/merchant-sidebar.php'; ?>
<main class="merchant-main">
<div class="merchant-topbar"><div><a href="/products" class="small text-secondary"> Products</a><h1 class="h3 fw-bold mt-1 mb-0">Edit product</h1></div></div>
<form method="post" enctype="multipart/form-data" class="row g-4">
<?= Csrf::field() ?>
<div class="col-lg-8"><section class="panel p-4"><?php require $root.'/app/Views/components/alert.php'; ?>
<div class="mb-3"><label class="form-label fw-semibold">Product name</label><input class="form-control form-control-lg" name="name" value="<?=e($product['name'])?>" required></div>
<div class="mb-4"><label class="form-label fw-semibold">Description</label><textarea class="form-control" name="description" rows="5"><?=e($product['description']??'')?></textarea></div><div class="mb-4"><label class="form-label fw-semibold">Options / sizes <span class="text-secondary">(optional)</span></label><input class="form-control" name="options" value="<?=e(implode(', ', json_decode($product['options_json'] ?? '[]', true) ?: []))?>" placeholder="e.g. Small, Medium, Large, XL"><div class="form-text">Use this for sizes, colours, materials or other customer choices.</div></div>
<div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Selling price (KSh)</label><input class="form-control form-control-lg" type="number" step="0.01" name="price" value="<?=e($product['price'])?>" required></div>
<div class="col-md-6"><label class="form-label">Original price</label><input class="form-control form-control-lg" type="number" step="0.01" name="compare_at_price" value="<?=e($product['compare_at_price']??'')?>"></div></div></section>
<section class="panel p-4 mt-4"><h2 class="h6 fw-bold mb-3">Inventory</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">SKU</label><input class="form-control" name="sku" value="<?=e($product['sku']??'')?>"></div><div class="col-md-6"><label class="form-label">Stock quantity</label><input class="form-control" type="number" name="stock_quantity" value="<?=e($product['stock_quantity'])?>"></div></div><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="track_inventory" id="trackInventory" <?=$product['track_inventory']?'checked':''?>><label class="form-check-label" for="trackInventory">Track inventory</label></div><div class="form-text mt-2">When enabled, stock below 1 makes the product unavailable on the storefront.</div></section></div>
<div class="col-lg-4"><section class="panel p-4">
<label class="form-label fw-semibold">Product image</label>
<?php if (!empty($product['image_path'])): ?><img src="<?=e($product['image_path'])?>" alt="" class="img-fluid rounded mb-3" style="max-height:180px;object-fit:cover"><?php endif; ?>
<input class="form-control mb-3" type="file" name="image" accept="image/jpeg,image/png,image/webp">
<div class="form-text mb-3">Upload a new JPG, PNG or WebP image (max 5MB).</div>
<label class="form-label fw-semibold">Category</label><select class="form-select mb-3" name="category_id"><option value="">Uncategorized</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id']?>" <?=$product['category_id']==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select><label class="form-label fw-semibold">Status</label><select class="form-select mb-3" name="status"><option value="draft" <?=$product['status']==='draft'?'selected':''?>>Draft</option><option value="active" <?=$product['status']==='active'?'selected':''?>>Active</option><option value="archived" <?=$product['status']==='archived'?'selected':''?>>Archived</option></select><div class="form-check"><input class="form-check-input" type="checkbox" name="featured" id="featured" <?=$product['featured']?'checked':''?>><label class="form-check-label" for="featured">Featured product</label></div></section><button class="btn btn-primary btn-lg w-100 mt-4">Save changes</button></div>
</form></main></div>
<?php $content=ob_get_clean(); $title='Edit product'; $merchantLayout = true; require $root.'/app/Views/layouts/app.php';
