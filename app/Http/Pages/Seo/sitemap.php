<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
use App\Bootstrap\App;
use App\Database\Database;
use function App\Support\base_url;
use function App\Support\shop_url;
new App();
$db=Database::connection();
$shops=$db->query("SELECT id, slug, updated_at FROM shops WHERE status='active' ORDER BY id DESC")->fetchAll();
$urls=[];
foreach($shops as $shop){
  $urls[]= ['loc'=>base_url(shop_url($shop['slug'])), 'lastmod'=>$shop['updated_at'] ?? null];
  $stmt=$db->prepare("SELECT slug, updated_at FROM products WHERE shop_id=:shop_id AND status='active' ORDER BY id DESC");
  $stmt->execute(['shop_id'=>(int)$shop['id']]);
  foreach($stmt->fetchAll() as $product){ $urls[]=['loc'=>base_url(shop_url($shop['slug']).'/product/'.$product['slug']), 'lastmod'=>$product['updated_at'] ?? null]; }
}
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as $u){ echo '<url><loc>'.htmlspecialchars($u['loc'],ENT_XML1,'UTF-8').'</loc>'; if($u['lastmod']) echo '<lastmod>'.htmlspecialchars(date('c',strtotime($u['lastmod'])),ENT_XML1,'UTF-8').'</lastmod>'; echo '</url>'; }
echo '</urlset>';
