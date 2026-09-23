<?php
use App\Support\Csrf;
?>
<?php
$brand=require dirname(__DIR__,3).'/config/branding.php';
use function App\Support\e;
use App\Support\Auth;
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top" aria-label="Main navigation">
<div class="container py-2">
<a class="navbar-brand brand-lockup" href="/"><span class="brand-mark">D</span><span class="fw-bold"><?=e($brand['name'])?></span></a>
<button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="mainNav">
<div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
<?php if (Auth::check()): ?>
<a class="nav-link" href="/dashboard">Dashboard</a>
<a class="nav-link" href="<?=e(App\Support\shop_url((string)App\Support\Session::get('shop_slug')))?>" target="_blank">My Shop</a>
<form method="post" action="/logout" class="m-0"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button type="submit" class="nav-link border-0 bg-transparent">Log out</button></form>
<?php else: ?>
<a class="nav-link" href="/">Home</a>
<a class="nav-link" href="/login">Log in</a>
<a class="btn btn-primary px-4" href="/register">Start selling</a>
<?php endif; ?>
</div></div></div></nav>
