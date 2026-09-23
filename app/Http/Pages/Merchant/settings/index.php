<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Support\Session;
new App();
if (!Session::get('shop_id')) { header('Location: /register'); exit; }
header('Location: /settings/shop');
exit;
