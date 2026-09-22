<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Support\Csrf;
use App\Support\Session;

new App();

if (!Session::get('_onboarding_user')) {
    header('Location: /register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $step = (int) ($_POST['step'] ?? 0);

        if ($step === 1) {
            $name = trim((string) ($_POST['business_name'] ?? ''));
            $type = trim((string) ($_POST['business_type'] ?? ''));

            if ($name === '' || $type === '') {
                Session::flash('error', 'Please complete the shop details.');
            } else {
                $user = Session::get('_onboarding_user');
                $db = Database::connection();

                $db->beginTransaction();

                try {
                    $stmt = $db->prepare(
                        'INSERT INTO users (first_name, last_name, phone, email, password_hash)
                         VALUES (:first_name, :last_name, :phone, :email, :password_hash)'
                    );

                    $stmt->execute([
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'] ?: null,
                        'phone' => $user['phone'],
                        'email' => $user['email'] ?: null,
                        'password_hash' => $user['password_hash'],
                    ]);

                    $userId = (int) $db->lastInsertId();

                    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

                    if ($slug === '') {
                        $slug = 'shop-' . $userId;
                    }

                    $check = $db->prepare('SELECT id FROM shops WHERE slug = :slug LIMIT 1');
                    $check->execute(['slug' => $slug]);

                    if ($check->fetch()) {
                        $slug .= '-' . $userId;
                    }

                    $shopStmt = $db->prepare(
                        'INSERT INTO shops (owner_id, name, slug, business_type)
                         VALUES (:owner_id, :name, :slug, :business_type)'
                    );

                    $shopStmt->execute([
                        'owner_id' => $userId,
                        'name' => $name,
                        'slug' => $slug,
                        'business_type' => $type,
                    ]);

                    $shopId = (int) $db->lastInsertId();

                    $settingsStmt = $db->prepare(
                        'INSERT INTO shop_settings (shop_id) VALUES (:shop_id)'
                    );

                    $settingsStmt->execute(['shop_id' => $shopId]);

                    $db->commit();

                    Session::forget('_onboarding_user');
                    Session::put('user_id', $userId);
                    Session::put('shop_id', $shopId);
                    Session::put('shop_slug', $slug);
                    Session::regenerate();

                    Session::flash('success', 'Your shop foundation has been created.');
                    header('Location: /onboarding-complete.php');
                    exit;
                } catch (\Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    Session::flash('error', 'We could not create your shop. Check your details and try again.');
                }
            }
        }
    }
}

require dirname(__DIR__) . '/app/Views/onboarding/step1.php';
