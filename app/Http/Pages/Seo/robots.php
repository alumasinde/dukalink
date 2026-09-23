<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use function App\Support\base_url;
new App();
header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /dashboard\nDisallow: /products\nDisallow: /categories\nDisallow: /orders\nDisallow: /customers\nDisallow: /settings\nDisallow: /cart\nDisallow: /checkout\nDisallow: /track\nSitemap: " . base_url('/sitemap.xml') . "\n";
