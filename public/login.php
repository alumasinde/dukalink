<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Support\Csrf;
use App\Support\Session;

new App();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        Session::flash('success', 'Login flow is ready for the authentication module.');
    }
}

require dirname(__DIR__) . '/app/Views/auth/login.php';
