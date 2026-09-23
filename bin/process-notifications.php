<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Notifications\NotificationService;

new App();
$limit = isset($argv[1]) ? (int)$argv[1] : 25;
$count = (new NotificationService(Database::connection()))->retryFailed($limit);
echo "Retried {$count} failed SMS notification(s).\n";
