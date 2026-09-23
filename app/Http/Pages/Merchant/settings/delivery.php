<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use function App\Support\e;
new App();
$db=Database::connection();
$merchant=Auth::requireMerchant($db); $shopId=(int)$merchant['id']; $repo=new ShopRepository($db); $shop=$repo->find($shopId);
if(!$shop){http_response_code(404);exit('Shop not found');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null)){Session::flash('error','Your form session expired. Please try again.');}
    else{
        $action=$_POST['action']??'settings';
        try{
            if($action==='settings'){
                $allowDelivery=isset($_POST['allow_delivery']); $allowPickup=isset($_POST['allow_store_pickup']); $allowCashPickup=isset($_POST['allow_cash_on_pickup']);
                $mode=($_POST['delivery_pricing_mode']??'flat')==='zone'?'zone':'flat';
                $flat=max(0,(float)($_POST['delivery_flat_fee']??0));
                $freeRaw=trim((string)($_POST['free_delivery_minimum']??''));
                if($flat<0) throw new RuntimeException('Delivery fee cannot be negative.');
                if($freeRaw!=='' && (float)$freeRaw<0) throw new RuntimeException('Free delivery threshold cannot be negative.');
                if(!$allowDelivery && !$allowPickup) throw new RuntimeException('Enable at least one fulfilment option: delivery or store pickup.');
                if($mode==='zone' && !$repo->deliveryZones($shopId,true)) throw new RuntimeException('Add at least one active delivery area before using area-based pricing.');
                $repo->saveDeliverySettings($shopId,[
                    'allow_delivery'=>$allowDelivery,'allow_store_pickup'=>$allowPickup,'allow_cash_on_pickup'=>$allowCashPickup,
                    'delivery_pricing_mode'=>$mode,'delivery_flat_fee'=>$flat,'free_delivery_minimum'=>$freeRaw,
                    'pickup_address'=>$_POST['pickup_address']??'','pickup_instructions'=>$_POST['pickup_instructions']??''
                ]);
                Session::flash('success','Delivery and pickup settings updated.');
            }elseif($action==='add_zone'){
                $name=trim((string)($_POST['name']??'')); $fee=(float)($_POST['fee']??0); $sort=(int)($_POST['sort_order']??0);
                if($name==='') throw new RuntimeException('Enter a delivery area name.');
                if($fee<0) throw new RuntimeException('Delivery fee cannot be negative.');
                $repo->createDeliveryZone($shopId,$name,$fee,$sort); Session::flash('success','Delivery area added.');
            }elseif($action==='update_zone'){
                $id=(int)($_POST['id']??0); $name=trim((string)($_POST['name']??'')); $fee=(float)($_POST['fee']??0); $sort=(int)($_POST['sort_order']??0); $status=$_POST['status']??'active';
                if($id<1||$name==='') throw new RuntimeException('Enter a valid delivery area.');
                $repo->updateDeliveryZone($shopId,$id,$name,$fee,$sort,$status); Session::flash('success','Delivery area updated.');
            }elseif($action==='delete_zone'){
                $repo->deleteDeliveryZone($shopId,(int)($_POST['id']??0)); Session::flash('success','Delivery area removed.');
            }
        }catch(Throwable $e){Session::flash('error',$e->getMessage());}
    }
    header('Location: /settings/delivery'); exit;
}
$shop=$repo->find($shopId); $zones=$repo->deliveryZones($shopId); $merchantShop=$shop; $merchantSection='delivery'; ob_start();
?>
<div class="merchant-shell">
<?php require $root.'/app/Views/components/merchant-sidebar.php'; ?>
<main class="merchant-main">
<div class="merchant-topbar"><div><div class="small text-secondary">Settings</div><h1 class="h3 fw-bold mb-0">Delivery & Pickup</h1><p class="small text-secondary mt-1 mb-0">Let customers choose delivery or collect their order from your store.</p></div><a href="<?=e(\App\Support\shop_url($shop['slug']))?>" target="_blank" class="btn btn-outline-dark">View storefront</a></div>
<?php require $root.'/app/Views/components/alert.php'; ?>
<form method="post"><input type="hidden" name="action" value="settings"><?=Csrf::field()?>
<div class="row g-4">
<div class="col-lg-7">
<section class="panel p-4 mb-4"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 fw-bold mb-1">Fulfilment options</h2><p class="small text-secondary mb-0">Choose what customers can select at checkout.</p></div></div>
<div class="delivery-setting-row mt-4"><div><strong>Store pickup</strong><p>Customers collect their order from your shop. No delivery fee is added.</p></div><div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" name="allow_store_pickup" id="allowPickup" <?=!empty($shop['allow_store_pickup'])?'checked':''?>></div></div>
<div class="delivery-setting-row"><div><strong>Delivery</strong><p>Customers provide their location and pay the configured delivery charge.</p></div><div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" name="allow_delivery" id="allowDelivery" <?=!empty($shop['allow_delivery'])?'checked':''?>></div></div>
</section>
<section class="panel p-4 mb-4" id="deliveryPricingPanel"><h2 class="h5 fw-bold mb-1">Delivery charges</h2><p class="small text-secondary">Never type a delivery charge at checkout. Dukame calculates it from these settings.</p>
<div class="mb-3"><label class="form-label fw-semibold">Pricing method</label><select class="form-select" name="delivery_pricing_mode" id="pricingMode"><option value="flat" <?=($shop['delivery_pricing_mode']??'flat')==='flat'?'selected':''?>>One delivery fee for every area</option><option value="zone" <?=($shop['delivery_pricing_mode']??'flat')==='zone'?'selected':''?>>Different fee by delivery area</option></select></div>
<div id="flatFeeBox" class="mb-3"><label class="form-label fw-semibold">Delivery fee</label><div class="input-group"><span class="input-group-text"><?=e($shop['currency']??'KES')?></span><input class="form-control" type="number" step="0.01" min="0" name="delivery_flat_fee" value="<?=e((string)($shop['delivery_flat_fee']??0))?>"></div><div class="form-text">Set 0 only if you intentionally offer free delivery.</div></div>
<div class="mb-3"><label class="form-label fw-semibold">Free delivery threshold <span class="text-secondary fw-normal">(optional)</span></label><div class="input-group"><span class="input-group-text"><?=e($shop['currency']??'KES')?></span><input class="form-control" type="number" step="0.01" min="0" name="free_delivery_minimum" value="<?=e($shop['free_delivery_minimum']!==null?(string)$shop['free_delivery_minimum']:'')?>" placeholder="e.g. 5000"></div><div class="form-text">Orders at or above this subtotal get free delivery.</div></div>
<div class="alert alert-light border small mb-0">Customers see the exact charge before placing the order. The server recalculates the fee, so a customer cannot change it from the browser.</div>
</section>
<section class="panel p-4"><h2 class="h5 fw-bold mb-1">Store pickup details</h2><p class="small text-secondary">Show customers where to collect their order.</p>
<div class="mb-3"><label class="form-label fw-semibold">Pickup address</label><textarea class="form-control" name="pickup_address" rows="2" maxlength="500" placeholder="Tom Mboya Street, Nairobi"><?=e($shop['pickup_address']??'')?></textarea></div>
<div class="mb-3"><label class="form-label fw-semibold">Pickup instructions <span class="text-secondary fw-normal">(optional)</span></label><textarea class="form-control" name="pickup_instructions" rows="3" maxlength="1000" placeholder="Collect from the front desk between 9am and 6pm."><?=e($shop['pickup_instructions']??'')?></textarea></div>
<div class="delivery-setting-row"><div><strong>Pay at pickup</strong><p>Let customers pay in cash when they collect their order.</p></div><div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" name="allow_cash_on_pickup" <?=!empty($shop['allow_cash_on_pickup'])?'checked':''?>></div></div>
</section>
</div>
<div class="col-lg-5">
<section class="panel p-4 mb-4"><h2 class="h5 fw-bold">Customer experience</h2><div class="fulfillment-preview mt-3"><div><strong>Delivery</strong><span><?=!empty($shop['allow_delivery'])?'Available':'Off'?></span></div><div><strong>Store pickup</strong><span><?=!empty($shop['allow_store_pickup'])?'Available':'Off'?></span></div><div><strong>Delivery pricing</strong><span><?=($shop['delivery_pricing_mode']??'flat')==='zone'?'By area':'Flat fee'?></span></div></div></section>
<div class="panel p-4 border-primary-subtle"><h2 class="h6 fw-bold">Save your setup</h2><p class="small text-secondary mb-3">Your checkout will use these settings immediately.</p><button class="btn btn-primary w-100" type="submit">Save delivery settings</button></div>
</div></div></form>
<section class="panel p-4 mt-4"><div class="d-flex justify-content-between align-items-start gap-3"><div><h2 class="h5 fw-bold mb-1">Delivery areas</h2><p class="small text-secondary mb-0">Use areas when different locations have different delivery charges.</p></div></div>
<div class="table-responsive mt-4"><table class="table align-middle"><thead><tr><th>Area</th><th>Fee</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($zones as $zone): $zoneForm='zone-form-'.(int)$zone['id']; ?><tr><td><input form="<?=e($zoneForm)?>" class="form-control" name="name" value="<?=e($zone['name'])?>" required></td><td><div class="input-group"><span class="input-group-text"><?=e($shop['currency']??'KES')?></span><input form="<?=e($zoneForm)?>" class="form-control" type="number" step="0.01" min="0" name="fee" value="<?=e((string)$zone['fee'])?>" required></div></td><td><input form="<?=e($zoneForm)?>" class="form-control" type="number" name="sort_order" value="<?=e((string)$zone['sort_order'])?>"></td><td><select form="<?=e($zoneForm)?>" class="form-select" name="status"><option value="active" <?=$zone['status']==='active'?'selected':''?>>Active</option><option value="hidden" <?=$zone['status']==='hidden'?'selected':''?>>Hidden</option></select></td><td class="text-nowrap"><form id="<?=e($zoneForm)?>" method="post" class="d-inline"><input type="hidden" name="action" value="update_zone"><?=Csrf::field()?><input type="hidden" name="id" value="<?=e((string)$zone['id'])?>"><button class="btn btn-sm btn-outline-dark">Save</button></form><form method="post" class="d-inline ms-1" onsubmit="return confirm('Remove this delivery area?');"><input type="hidden" name="action" value="delete_zone"><?=Csrf::field()?><input type="hidden" name="id" value="<?=e((string)$zone['id'])?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach; ?>
<?php if(!$zones): ?><tr><td colspan="5" class="text-secondary small">No delivery areas yet. Add one below if you want area-based pricing.</td></tr><?php endif; ?></tbody></table></div>
<div class="border-top pt-4 mt-3"><h3 class="h6 fw-bold">Add delivery area</h3><form method="post" class="row g-2 align-items-end"><input type="hidden" name="action" value="add_zone"><?=Csrf::field()?><div class="col-md-5"><label class="form-label small fw-semibold">Area name</label><input class="form-control" name="name" placeholder="Westlands" required></div><div class="col-md-3"><label class="form-label small fw-semibold">Fee</label><div class="input-group"><span class="input-group-text"><?=e($shop['currency']??'KES')?></span><input class="form-control" type="number" step="0.01" min="0" name="fee" placeholder="250" required></div></div><div class="col-md-2"><label class="form-label small fw-semibold">Order</label><input class="form-control" type="number" name="sort_order" value="0"></div><div class="col-md-2"><button class="btn btn-outline-dark w-100">Add area</button></div></form></div>
</section>
</main></div>
<script>document.addEventListener('DOMContentLoaded',()=>{const mode=document.getElementById('pricingMode'),flat=document.getElementById('flatFeeBox'),delivery=document.getElementById('allowDelivery');const sync=()=>{flat.hidden=mode.value==='zone'; document.getElementById('deliveryPricingPanel').classList.toggle('opacity-50',!delivery.checked);};mode?.addEventListener('change',sync);delivery?.addEventListener('change',sync);sync();});</script>
<?php $content=ob_get_clean();$title='Delivery & Pickup';$merchantLayout=true;require $root.'/app/Views/layouts/app.php';
