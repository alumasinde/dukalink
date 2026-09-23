<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Subscriptions\SubscriptionPlanRepository;
use App\Support\Csrf;
use App\Support\PlatformAuth;
use App\Support\Session;
use function App\Support\e;
use function App\Support\slugify;

new App();
$db = Database::connection();
$platformUser = PlatformAuth::requireAdmin($db);
$repo = new SubscriptionPlanRepository($db);
$canManagePlans = ($platformUser['role'] ?? '') === 'super_admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManagePlans) { Session::flash('error', 'Only a super administrator can change subscription plans.'); header('Location: '.PlatformAuth::basePath().'/plans'); exit; }
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $slug = slugify((string)($_POST['slug'] ?? $name));
                if ($name === '' || $slug === '') throw new RuntimeException('Plan name is required.');
                $id = $repo->create([
                    'name'=>$name,'slug'=>$slug,'description'=>$_POST['description']??'',
                    'monthly_price'=>$_POST['monthly_price']??0,'annual_price'=>$_POST['annual_price']??0,
                    'currency'=>$_POST['currency']??'KES','trial_days'=>$_POST['trial_days']??0,
                    'is_active'=>isset($_POST['is_active']),'is_public'=>isset($_POST['is_public']),
                    'is_featured'=>isset($_POST['is_featured']),'sort_order'=>$_POST['sort_order']??0
                ]);
                Session::flash('success','Plan created.');
                header('Location: '.PlatformAuth::basePath().'/plans?edit='.$id); exit;
            }
            if ($action === 'update') {
                $id=(int)($_POST['id']??0);
                $name=trim((string)($_POST['name']??''));
                $slug=slugify((string)($_POST['slug']??$name));
                if($id<1||$name===''||$slug==='') throw new RuntimeException('Plan details are required.');
                $repo->update($id,[
                    'name'=>$name,'slug'=>$slug,'description'=>$_POST['description']??'',
                    'monthly_price'=>$_POST['monthly_price']??0,'annual_price'=>$_POST['annual_price']??0,
                    'currency'=>$_POST['currency']??'KES','trial_days'=>$_POST['trial_days']??0,
                    'is_active'=>isset($_POST['is_active']),'is_public'=>isset($_POST['is_public']),
                    'is_featured'=>isset($_POST['is_featured']),'sort_order'=>$_POST['sort_order']??0
                ]);
                $keys=$_POST['feature_key']??[]; $names=$_POST['feature_name']??[]; $enabled=$_POST['feature_enabled']??[]; $limits=$_POST['feature_limit']??[]; $orders=$_POST['feature_sort']??[];
                foreach($keys as $i=>$key){
                    $key=trim((string)$key); $fname=trim((string)($names[$i]??''));
                    if($key===''||$fname==='') continue;
                    $raw=trim((string)($limits[$i]??'')); $limit=$raw===''?null:(int)$raw;
                    $repo->saveFeature($id,$key,$fname,in_array((string)$i,$enabled,true),$limit,(int)($orders[$i]??$i*10));
                }
                Session::flash('success','Plan updated. Changes apply immediately to new entitlement checks.');
                header('Location: '.PlatformAuth::basePath().'/plans?edit='.$id); exit;
            }
        } catch (Throwable $ex) { Session::flash('error','Could not save plan: '.$ex->getMessage()); }
    }
}

$plans=$repo->all();
$editId=(int)($_GET['edit']??0);
$edit=$editId>0?$repo->find($editId):null;
ob_start(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><div class="platform-kicker">Billing</div><h2 class="platform-page-title">Subscription plans</h2><p class="platform-muted mb-0">Configure the plans and entitlements merchants see across Dukame.</p></div>
    <a href="<?= e(PlatformAuth::basePath()) ?>/subscriptions" class="btn btn-outline-dark">View subscriptions</a>
</div>
<?php require $root.'/app/Views/components/alert.php'; ?>
<div class="row g-4">
    <div class="col-xl-4">
        <section class="platform-panel"><div class="platform-panel-head"><div><h2>Configured plans</h2><p>Public plans appear on the landing page automatically.</p></div><span class="platform-muted"><?= count($plans) ?> plans</span></div><div class="list-group list-group-flush">
        <?php foreach($plans as $plan): ?><a class="list-group-item list-group-item-action <?= $editId===(int)$plan['id']?'platform-plan-selected':'' ?> py-3" href="<?= e(PlatformAuth::basePath()) ?>/plans?edit=<?= (int)$plan['id'] ?>"><div class="d-flex justify-content-between gap-3"><div><strong><?= e($plan['name']) ?></strong><div class="platform-muted"><?= e($plan['slug']) ?></div></div><span class="status-badge <?= $plan['is_active']?'status-active':'status-draft' ?>"><?= $plan['is_active']?'Active':'Inactive' ?></span></div><div class="mt-2 fw-semibold"><?= e($plan['currency']) ?> <?= e(number_format((float)$plan['monthly_price'],0)) ?> <span class="platform-muted fw-normal">/ month</span></div></a><?php endforeach; ?>
        </div></section>
    </div>
    <div class="col-xl-8">
        <section class="platform-panel mb-4"><div class="platform-panel-head"><div><h2><?= $edit ? 'Edit plan' : 'Create plan' ?></h2><p>Pricing, visibility and trial settings.</p></div></div><form method="post" class="p-4"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="<?= $edit?'update':'create' ?>"><?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?><div class="row g-3"><div class="col-md-6"><label class="form-label">Plan name</label><input class="form-control" name="name" required value="<?= e($edit['name']??'') ?>"></div><div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" value="<?= e($edit['slug']??'') ?>"></div><div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description']??'') ?>"></div><div class="col-md-4"><label class="form-label">Monthly price</label><input class="form-control" type="number" min="0" step="0.01" name="monthly_price" value="<?= e((string)($edit['monthly_price']??0)) ?>"></div><div class="col-md-4"><label class="form-label">Annual price</label><input class="form-control" type="number" min="0" step="0.01" name="annual_price" value="<?= e((string)($edit['annual_price']??0)) ?>"></div><div class="col-md-4"><label class="form-label">Currency</label><input class="form-control" maxlength="3" name="currency" value="<?= e($edit['currency']??'KES') ?>"></div><div class="col-md-4"><label class="form-label">Trial days</label><input class="form-control" type="number" min="0" name="trial_days" value="<?= e((string)($edit['trial_days']??0)) ?>"></div><div class="col-md-4"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e((string)($edit['sort_order']??0)) ?>"></div><div class="col-md-4 d-flex align-items-end gap-3 pb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($edit['is_active'])||!$edit?'checked':'' ?>><label class="form-check-label">Active</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" <?= !empty($edit['is_public'])||!$edit?'checked':'' ?>><label class="form-check-label">Public</label></div></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" <?= !empty($edit['is_featured'])?'checked':'' ?>><label class="form-check-label">Featured plan</label></div></div></div><div class="mt-4"><button class="btn btn-primary"><?= $edit?'Save changes':'Create plan' ?></button></div></form></section>
        <?php if($edit): ?><section class="platform-panel"><div class="platform-panel-head"><div><h2>Entitlements</h2><p>Limits and features enforced by the subscription service.</p></div></div><form method="post" class="p-4"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><input type="hidden" name="name" value="<?= e($edit['name']) ?>"><input type="hidden" name="slug" value="<?= e($edit['slug']) ?>"><input type="hidden" name="description" value="<?= e($edit['description']??'') ?>"><input type="hidden" name="monthly_price" value="<?= e((string)$edit['monthly_price']) ?>"><input type="hidden" name="annual_price" value="<?= e((string)$edit['annual_price']) ?>"><input type="hidden" name="currency" value="<?= e($edit['currency']) ?>"><input type="hidden" name="trial_days" value="<?= e((string)$edit['trial_days']) ?>"><input type="hidden" name="sort_order" value="<?= e((string)$edit['sort_order']) ?>"><?php if(!empty($edit['is_active'])):?><input type="hidden" name="is_active" value="1"><?php endif;?><?php if(!empty($edit['is_public'])):?><input type="hidden" name="is_public" value="1"><?php endif;?><?php if(!empty($edit['is_featured'])):?><input type="hidden" name="is_featured" value="1"><?php endif;?>
        <?php $features=$edit['features']??[]; foreach($features as $i=>$feature): ?><div class="row g-2 align-items-end mb-3"><div class="col-md-3"><label class="form-label small">Key</label><input class="form-control" name="feature_key[]" value="<?= e($feature['feature_key']) ?>"></div><div class="col-md-4"><label class="form-label small">Name</label><input class="form-control" name="feature_name[]" value="<?= e($feature['feature_name']) ?>"></div><div class="col-md-2"><label class="form-label small">Limit</label><input class="form-control" type="number" name="feature_limit[]" value="<?= $feature['limit_value']===null?'':e((string)$feature['limit_value']) ?>" placeholder="Unlimited"></div><div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="feature_enabled[]" value="<?= $i ?>" <?= $feature['enabled']?'checked':'' ?>><label class="form-check-label">Enabled</label></div></div><input type="hidden" name="feature_sort[]" value="<?= (int)$feature['sort_order'] ?>"></div><?php endforeach; ?><div class="d-flex justify-content-between align-items-center"><span class="platform-muted">Unlimited = leave the limit empty.</span><button class="btn btn-primary">Save entitlements</button></div></form></section><?php endif; ?>
    </div>
</div>
<?php $content=ob_get_clean(); $title='Plans'; $platformSection='plans'; require $root.'/app/Views/layouts/platform.php';
