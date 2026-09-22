<?php
use function App\Support\e; use function App\Support\asset_url;
$title=$title??'Dukame'; $content=$content??''; $brand=require dirname(__DIR__,3).'/config/branding.php';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> · <?=e($brand['name'])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/variables.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('/assets/css/components.css'))?>"></head><body class="auth-page"><main><?=$content?></main></body></html>
