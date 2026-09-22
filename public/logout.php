<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Support\Auth;

new App();
Auth::logout();
header('Location: /login');
exit;
