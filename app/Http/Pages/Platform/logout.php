<?php
declare(strict_types=1);
$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use App\Support\PlatformAuth;
use App\Support\Csrf;
use App\Modules\Platform\PlatformAuditService;
new App();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
$db = Database::connection();
if (PlatformAuth::check()) {
    $user = PlatformAuth::requireAdmin($db);
    (new PlatformAuditService($db))->record((int)$user['id'], 'platform_user.logout', 'Platform user signed out', 'platform_user', (int)$user['id']);
}
$base = PlatformAuth::basePath();
PlatformAuth::logout();
header('Location: ' . $base . '/login');
exit;
