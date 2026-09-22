<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Modules\Subscriptions\EntitlementService;
use App\Modules\Subscriptions\SubscriptionPlanRepository;
use App\Modules\Subscriptions\SubscriptionMpesaService;
use App\Support\Auth;
use App\Support\Session;
use App\Support\Csrf;
use function App\Support\e;

new App();
$db=Database::connection();
$merchant=Auth::requireMerchant($db); $shopId=(int)$merchant['id'];
$plans=(new SubscriptionPlanRepository($db))->publicPlans();
$entitlements=new EntitlementService($db); $subscription=$entitlements->subscription($shopId); $usage=$entitlements->usageSummary($shopId);
$merchantShop=(new ShopRepository($db))->find($shopId); $merchantSection='subscription';
$subscriptionMpesa=(new SubscriptionMpesaService($db))->enabled();
$ownerPhone=(string)($merchant['user_phone']??$merchant['phone']??'');
ob_start(); ?>
<div class="merchant-shell">
<?php require dirname(__DIR__,2).'/app/Views/components/merchant-sidebar.php'; ?>
<main class="merchant-main">
<div class="merchant-topbar"><div><div class="small text-secondary">Account</div><h1 class="h3 fw-bold mb-0">Subscription</h1></div></div>
<?php require dirname(__DIR__,2).'/app/Views/components/alert.php'; ?>
<?php if($subscription): ?>
<section class="subscription-current panel mb-4"><div class="p-4"><div class="d-flex flex-column flex-md-row justify-content-between gap-3"><div><span class="eyebrow">CURRENT PLAN</span><h2 class="h4 fw-bold mt-2 mb-1"><?= e($subscription['plan_name']) ?> <span class="status-badge status-<?= e($subscription['status']) ?>"><?= e(ucwords(str_replace('_',' ',$subscription['status']))) ?></span></h2><p class="text-secondary mb-0"><?php if($subscription['status']==='trial'): ?>Your trial ends <?= e(date('F j, Y',strtotime($subscription['trial_ends_at']))) ?>.<?php elseif(!empty($subscription['current_period_end'])): ?>Your current period ends <?= e(date('F j, Y',strtotime($subscription['current_period_end']))) ?>.<?php else: ?>Subscription details.<?php endif; ?></p></div><div class="text-md-end"><div class="h4 fw-bold mb-0"><?= e($subscription['currency']) ?> <?= number_format((float)($subscription['billing_interval']==='annual'?$subscription['annual_price']:$subscription['monthly_price']),0) ?></div><div class="small text-secondary">/ <?= e($subscription['billing_interval']) ?></div></div></div>
<div class="row g-3 mt-2"><?php foreach($usage as $item): ?><div class="col-md-4"><div class="subscription-usage-mini"><div class="d-flex justify-content-between small fw-semibold"><span><?= e($item['label']) ?></span><span><?= $item['unlimited']?'Unlimited':e($item['usage'].' / '.$item['limit']) ?></span></div><?php if(!$item['unlimited']): ?><div class="progress mt-2"><div class="progress-bar" style="width:<?= $item['limit']>0?min(100,round(($item['usage']/$item['limit'])*100)):100 ?>%"></div></div><?php endif; ?></div></div><?php endforeach; ?></div>
</div></section>
<?php else: ?><div class="alert alert-warning">No subscription is attached to this shop. Contact Dukame support before adding more catalogue items.</div><?php endif; ?>
<h2 class="h5 fw-bold mb-3">Available plans</h2><div class="row g-4"><?php foreach($plans as $plan): ?><div class="col-md-6 col-xl-4"><article class="pricing-card h-100 <?= !empty($plan['is_featured'])?'pricing-card-featured':'' ?>"><?php if(!empty($plan['is_featured'])): ?><span class="pricing-popular">Popular</span><?php endif; ?><h3 class="h5 fw-bold"><?= e($plan['name']) ?></h3><p class="small text-secondary min-h-plan"><?= e($plan['description']??'') ?></p><div class="pricing-price"><span><?= e($plan['currency']) ?> <?= number_format((float)$plan['monthly_price'],0) ?></span><small>/ month</small></div><div class="pricing-features"><?php foreach($plan['features'] as $feature): ?><div class="d-flex gap-2"><span class="text-success fw-bold">✓</span><span><?= e($feature['feature_name']) ?><?php if($feature['limit_value']!==null): ?> <span class="text-secondary">(<?= (int)$feature['limit_value']<0?'Unlimited':(int)$feature['limit_value'] ?>)</span><?php endif; ?></span></div><?php endforeach; ?></div><?php if($subscription && (int)$subscription['plan_id']===(int)$plan['id'] && $subscription['status']==='active'): ?><button class="btn btn-light border w-100 mt-4" disabled>Current plan</button><?php else: ?><button type="button" class="btn btn-primary w-100 mt-4" data-subscribe-plan="<?= (int)$plan['id'] ?>" data-plan-name="<?= e($plan['name']) ?>" data-monthly="<?= e((string)$plan['monthly_price']) ?>" data-annual="<?= e((string)$plan['annual_price']) ?>" data-currency="<?= e((string)$plan['currency']) ?>" <?= $subscriptionMpesa?'':'disabled' ?>><?= $subscriptionMpesa?'Choose plan':'Payments coming soon' ?></button><?php endif; ?></article></div><?php endforeach; ?></div>
<div class="modal fade" id="subscriptionPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
    <div class="modal-header"><div><div class="small text-secondary">Upgrade or renew</div><h2 class="h5 fw-bold mb-0" id="subscriptionPlanName">Subscription</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form id="subscriptionPaymentForm"><div class="modal-body">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="plan_id" id="subscriptionPlanId">
      <div class="mb-3"><label class="form-label">Billing period</label><div class="btn-group w-100" role="group"><input class="btn-check" type="radio" name="billing_interval" id="billMonthly" value="monthly" checked><label class="btn btn-outline-dark" for="billMonthly">Monthly <span id="monthlyPrice"></span></label><input class="btn-check" type="radio" name="billing_interval" id="billAnnual" value="annual"><label class="btn btn-outline-dark" for="billAnnual">Annual <span id="annualPrice"></span></label></div></div>
      <div class="mb-3"><label class="form-label">M-Pesa phone number</label><input class="form-control" name="phone" value="<?= e($ownerPhone) ?>" placeholder="07XX XXX XXX" required></div>
      <div class="alert alert-info mb-0">After you continue, Dukame will send an M-Pesa STK Push to this number. Your plan changes only after Safaricom confirms the payment.</div>
      <div id="subscriptionPaymentMessage" class="mt-3 small"></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="subscriptionPayButton">Send M-Pesa prompt</button></div></form>
  </div></div>
</div>
<script>
(() => {
 const modalEl=document.getElementById('subscriptionPaymentModal'); if(!modalEl) return;
 const modal=new bootstrap.Modal(modalEl), form=document.getElementById('subscriptionPaymentForm'), nameEl=document.getElementById('subscriptionPlanName'), idEl=document.getElementById('subscriptionPlanId'), msg=document.getElementById('subscriptionPaymentMessage'), pay=document.getElementById('subscriptionPayButton');
 let prices={monthly:'',annual:''};
 document.querySelectorAll('[data-subscribe-plan]').forEach(btn=>btn.addEventListener('click',()=>{idEl.value=btn.dataset.subscribePlan;nameEl.textContent=btn.dataset.planName;prices={monthly:btn.dataset.monthly,annual:btn.dataset.annual};const currency=btn.dataset.currency||'KES';document.getElementById('monthlyPrice').textContent=currency+' '+Number(prices.monthly).toLocaleString();document.getElementById('annualPrice').textContent=currency+' '+Number(prices.annual).toLocaleString();msg.textContent='';pay.disabled=false;modal.show();}));
 form.addEventListener('submit',async e=>{e.preventDefault();pay.disabled=true;msg.className='mt-3 small text-secondary';msg.textContent='Sending M-Pesa prompt…';try{const r=await fetch('/subscription/pay',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)});const j=await r.json();if(!j.success)throw new Error(j.error?.message||'Could not start payment.');msg.className='mt-3 small text-secondary';msg.textContent='STK Push sent. Check your phone and enter your M-Pesa PIN.';const paymentId=j.data.payment_id;let tries=0;const poll=setInterval(async()=>{tries++;try{const sr=await fetch('/subscription/status?payment_id='+encodeURIComponent(paymentId),{headers:{'Accept':'application/json'}});const sj=await sr.json();const st=sj.data?.payment?.status;if(st==='paid'){clearInterval(poll);msg.className='mt-3 small text-success fw-semibold';msg.textContent='Payment received. Your subscription is now active.';setTimeout(()=>location.reload(),900);}else if(st==='failed'||st==='cancelled'){clearInterval(poll);pay.disabled=false;msg.className='mt-3 small text-danger';msg.textContent=sj.data?.payment?.result_description||'Payment was not completed. You can try again.';}else if(tries>=30){clearInterval(poll);pay.disabled=false;msg.className='mt-3 small text-secondary';msg.textContent='Payment is still pending. You can close this window and return later.';}}catch(_){if(tries>=30){clearInterval(poll);pay.disabled=false;msg.textContent='Payment status is still pending.';}}},3000);}catch(err){pay.disabled=false;msg.className='mt-3 small text-danger';msg.textContent=err.message;}});
})();
</script>
</main></div>
<?php $content=ob_get_clean(); $title='Subscription'; $merchantLayout=true; require dirname(__DIR__,2).'/app/Views/layouts/app.php';
