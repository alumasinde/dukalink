<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
use App\Bootstrap\App;
use App\Api\V1\Controllers\SubscriptionController;
new App();
SubscriptionController::status();
