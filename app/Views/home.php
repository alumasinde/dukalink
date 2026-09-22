<?php ob_start(); ?>
<section class="hero-section">
    <div class="container py-5">
        <div class="row align-items-center g-5 py-lg-5">
            <div class="col-lg-7">
                <span class="badge rounded-pill text-bg-light border px-3 py-2 mb-3">
                    Built for Kenyan businesses
                </span>

                <h1 class="display-4 fw-bold mb-3">
                    Your shop online.<br>
                    <span class="text-primary">Simple.</span>
                </h1>

                <p class="lead text-secondary mb-4">
                    Create your shop, add your products, share your link and start
                    receiving orders without making your customers create an account.
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="/register.php" class="btn btn-primary btn-lg px-4">
                        Create my shop
                    </a>
                    <a href="/login.php" class="btn btn-outline-dark btn-lg px-4">
                        Merchant login
                    </a>
                </div>

                <div class="d-flex flex-wrap gap-4 mt-4 small text-secondary">
                    <span>✓ Easy onboarding</span>
                    <span>✓ M-Pesa ready</span>
                    <span>✓ Customer-friendly checkout</span>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="store-preview shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bold">Pendea Boutique</div>
                            <div class="small text-secondary">dukame.app/pendeaboutique</div>
                        </div>
                        <span class="badge text-bg-success">LIVE</span>
                    </div>

                    <div class="bg-light rounded-4 p-3 mb-3">
                        <div class="small text-secondary mb-1">Today's sales</div>
                        <div class="h3 fw-bold mb-0">KSh 18,500</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="mini-stat">
                                <div class="small text-secondary">Orders</div>
                                <strong>24</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mini-stat">
                                <div class="small text-secondary">Customers</div>
                                <strong>18</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container py-lg-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">From idea to online shop</h2>
            <p class="text-secondary">Dukame keeps the process short and practical.</p>
        </div>

        <div class="row g-4">
            <?php
            $steps = [
                ['01', 'Create your shop', 'Tell us your business name and choose your shop link.'],
                ['02', 'Add products', 'Upload products and prices. You can keep adding them later.'],
                ['03', 'Share and sell', 'Send your shop link to customers and receive orders.'],
            ];
            foreach ($steps as [$number, $heading, $text]):
            ?>
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="step-number"><?= $number ?></div>
                        <h3 class="h5 fw-bold mt-4"><?= $heading ?></h3>
                        <p class="text-secondary mb-0"><?= $text ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
$title = 'Simple online shops for Kenyan businesses';
require __DIR__ . '/layouts/app.php';
