<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Support\PlatformAuth;
new App();
$base = PlatformAuth::basePath();
PlatformAuth::logout();
header('Location: ' . $base . '/login');
exit;
