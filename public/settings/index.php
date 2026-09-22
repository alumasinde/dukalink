<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Support\Session;
new App();
if (!Session::get('shop_id')) { header('Location: /register'); exit; }
header('Location: /settings/shop');
exit;
