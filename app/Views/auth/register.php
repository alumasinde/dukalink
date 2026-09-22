<?php ob_start(); ?>
<div class="auth-shell">
    <div class="auth-card">
        <div class="text-center mb-4">
            <a href="/" class="brand-mark">Dukame</a>
            <h1 class="h3 fw-bold mt-4 mb-2">Create your account</h1>
            <p class="text-secondary mb-0">Then we'll help you set up your shop.</p>
        </div>

        <?php require __DIR__ . '/../components/alert.php'; ?>

        <form method="post" action="/register">
            <?= \App\Support\Csrf::field() ?>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">First name</label>
                    <input type="text" name="first_name" class="form-control form-control-lg" required>
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Last name</label>
                    <input type="text" name="last_name" class="form-control form-control-lg">
                </div>

                <div class="col-12">
                    <label class="form-label">Phone number</label>
                    <input type="tel" name="phone" class="form-control form-control-lg" placeholder="0712 345 678" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Email <span class="text-secondary">(optional)</span></label>
                    <input type="email" name="email" class="form-control form-control-lg">
                </div>

                <div class="col-12">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control form-control-lg" minlength="8" required>
                </div>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-4">
                Continue to shop setup
            </button>
        </form>

        <p class="text-center text-secondary small mt-4 mb-0">
            Already have an account?
            <a href="/login">Log in</a>
        </p>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Create your account';
require __DIR__ . '/../layouts/auth.php';
