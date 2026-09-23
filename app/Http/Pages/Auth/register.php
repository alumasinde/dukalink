<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\Validator;

new App();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];

        if ($error = Validator::required($firstName, 'First name')) {
            $errors[] = $error;
        }

        if ($error = Validator::phone($phone)) {
            $errors[] = $error;
        }

        if ($error = Validator::password($password)) {
            $errors[] = $error;
        }

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
        } else {
            Session::put('_onboarding_user', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            Session::regenerate();
            header('Location: /onboarding.php');
            exit;
        }
    }
}

require $root . '/app/Views/auth/register.php';
