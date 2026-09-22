<?php ob_start(); ?>
<div class="auth-shell">
    <div class="auth-card">
        <div class="text-center mb-4">
            <a href="/" class="brand-mark">Dukame</a>
            <h1 class="h3 fw-bold mt-4 mb-2">Welcome back</h1>
            <p class="text-secondary mb-0">Log in to manage your shop.</p>
        </div>

        <?php require __DIR__ . '/../components/alert.php'; ?>

        <form method="post" action="/login">
            <?= \App\Support\Csrf::field() ?>

            <div class="mb-3">
                <label class="form-label">Phone number</label>
                <input type="tel" name="phone" class="form-control form-control-lg" placeholder="0712 345 678" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-2">Log in</button>
        </form>

        <p class="text-center text-secondary small mt-4 mb-0">
            New to Dukame?
            <a href="/register">Create your shop</a>
        </p>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Merchant login';
require __DIR__ . '/../layouts/auth.php';
