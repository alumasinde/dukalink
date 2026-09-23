<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use App\Support\PlatformAuth;
use App\Modules\Platform\PlatformAuditService;
new App();
$db = Database::connection();
$user = PlatformAuth::check() ? PlatformAuth::requireAdmin($db) : null;
if ($user) (new PlatformAuditService($db))->record((int)$user['id'], 'platform_user.logout', 'Platform user signed out', 'platform_user', (int)$user['id']);
$base = PlatformAuth::basePath();
PlatformAuth::logout();
header('Location: ' . $base . '/login');
exit;
