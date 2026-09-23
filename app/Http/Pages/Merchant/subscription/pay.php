<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';
use App\Bootstrap\App;
use App\Api\V1\Controllers\SubscriptionController;
new App();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
SubscriptionController::initiate();
