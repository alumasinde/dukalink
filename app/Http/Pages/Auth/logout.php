<?php
declare(strict_types=1);
$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Support\Auth;
use App\Support\Csrf;
new App();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
Auth::logout();
header('Location: /login');
exit;
