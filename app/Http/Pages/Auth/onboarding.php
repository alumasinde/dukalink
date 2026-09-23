<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Modules\Shops\ShopRepository;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\Validator;
use function App\Support\slugify;
use function App\Support\reserved_shop_slug;

new App();

if (!Session::get('_onboarding_user')) {
    header('Location: /register');
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
                $shops = new ShopRepository($db);

                $slugInput = trim((string) ($_POST['shop_slug'] ?? ''));
                $slug = slugify($slugInput !== '' ? $slugInput : $name);

                if ($slug === '' || strlen($slug) < 3) {
                    Session::flash('error', 'Your shop link must contain at least 3 letters or numbers.');
                } elseif (strlen($slug) > 80) {
                    Session::flash('error', 'Your shop link is too long.');
                } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    Session::flash('error', 'Use letters, numbers and single hyphens only.');
                } elseif (reserved_shop_slug($slug)) {
                    Session::flash('error', 'That shop link is reserved. Please choose another one.');
                } elseif ($shops->slugExists($slug)) {
                    Session::flash('error', 'That shop link is already taken. Try another one.');
                } else {
                    $db->beginTransaction();

                    try {
                        $stmt = $db->prepare(
                            'INSERT INTO users (first_name, last_name, phone, email, password_hash, role)
                             VALUES (:first_name, :last_name, :phone, :email, :password_hash, \'merchant\')'
                        );

                        $stmt->execute([
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'] ?: null,
                            'phone' => $user['phone'],
                            'email' => $user['email'] ?: null,
                            'password_hash' => $user['password_hash'],
                        ]);

                        $userId = (int) $db->lastInsertId();

                        $shopStmt = $db->prepare(
                            "INSERT INTO shops (owner_id, name, slug, business_type, status)
                             VALUES (:owner_id, :name, :slug, :business_type, 'draft')"
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

                        // Every new merchant starts with the currently configured public Basic plan trial.
                        $planStmt = $db->query(
                            "SELECT id, trial_days FROM subscription_plans WHERE slug = 'basic' AND is_active = 1 LIMIT 1"
                        );
                        $basicPlan = $planStmt->fetch();
                        if (!$basicPlan) {
                            throw new \RuntimeException('No default subscription plan is configured.');
                        }
                        $trialDays = max(0, (int)$basicPlan['trial_days']);
                        $now = new \DateTimeImmutable('now');
                        $trialEnd = $trialDays > 0 ? $now->modify('+' . $trialDays . ' days') : $now;
                        $subscriptionStmt = $db->prepare(
                            "INSERT INTO subscriptions
                             (shop_id, plan_id, status, billing_interval, starts_at, trial_ends_at, current_period_start, current_period_end, grace_ends_at)
                             VALUES (:shop_id, :plan_id, :status, 'monthly', :starts_at, :trial_ends_at, :period_start, :period_end, :grace_ends_at)"
                        );
                        $subscriptionStmt->execute([
                            'shop_id' => $shopId,
                            'plan_id' => (int)$basicPlan['id'],
                            'status' => $trialDays > 0 ? 'trial' : 'active',
                            'starts_at' => $now->format('Y-m-d H:i:s'),
                            'trial_ends_at' => $trialDays > 0 ? $trialEnd->format('Y-m-d H:i:s') : null,
                            'period_start' => $now->format('Y-m-d H:i:s'),
                            'period_end' => $trialEnd->format('Y-m-d H:i:s'),
                            'grace_ends_at' => $trialEnd->modify('+3 days')->format('Y-m-d H:i:s'),
                        ]);

                        $db->commit();

                        Session::forget('_onboarding_user');
                        Auth::login($userId, $shopId, $slug);

                        Session::flash('success', 'Your shop was created. Complete setup, then publish it when you are ready.');
                        header('Location: /onboarding/complete');
                        exit;
                    } catch (\Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }

                        Session::flash('error', 'We could not create your shop. Please try again.');
                    }
                }
            }
        }
    }
}

require $root . '/app/Views/onboarding/step1.php';
