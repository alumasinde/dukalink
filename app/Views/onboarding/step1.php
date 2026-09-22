<?php ob_start(); ?>
<div class="onboarding-shell">
    <div class="onboarding-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="brand-mark">Dukame</div>
            <span class="small text-secondary">Step 1 of 4</span>
        </div>

        <div class="progress mb-4" role="progressbar">
            <div class="progress-bar" style="width: 25%"></div>
        </div>

        <h1 class="h3 fw-bold">Let's set up your shop</h1>
        <p class="text-secondary mb-4">
            Start with the basics. You can change these details later.
        </p>

        <?php require __DIR__ . '/../components/alert.php'; ?>

        <form method="post" action="/onboarding.php">
            <?= \App\Support\Csrf::field() ?>
            <input type="hidden" name="step" value="1">

            <div class="mb-3">
                <label class="form-label">What's your business called?</label>
                <input
                    type="text"
                    name="business_name"
                    class="form-control form-control-lg"
                    placeholder="e.g. Pendea Boutique"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Your shop link</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-end-0">dukame.app/shop/</span>
                    <input
                        type="text"
                        name="shop_slug"
                        class="form-control border-start-0"
                        placeholder="pendeaboutique"
                        pattern="[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*"
                        minlength="3"
                        maxlength="80"
                    >
                </div>
                <div class="form-text">Use letters, numbers and hyphens. You can change this later.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">What do you sell?</label>
                <select name="business_type" class="form-select form-select-lg" required>
                    <option value="">Choose a category</option>
                    <option>Fashion & Clothing</option>
                    <option>Beauty & Cosmetics</option>
                    <option>Electronics</option>
                    <option>Food & Drinks</option>
                    <option>Home & Living</option>
                    <option>Health & Wellness</option>
                    <option>Other</option>
                </select>
            </div>

            <button class="btn btn-primary btn-lg w-100">Continue →</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Set up your shop';
require __DIR__ . '/../layouts/auth.php';
