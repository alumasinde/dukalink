<?php
declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\ApiAuth;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;
use App\Database\Database;
use App\Modules\Products\ProductRepository;
use App\Modules\Categories\CategoryRepository;
use App\Modules\Subscriptions\EntitlementService;
use function App\Support\slugify;

final class CatalogController
{
    public static function products(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
        $products = (new ProductRepository(Database::connection()))->allForShop((int)$shop['id'], $status ?: null);
        JsonResponse::send($products);
    }

    public static function createProduct(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $data = Request::json();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') JsonResponse::error('Product name is required.', 422, 'validation_error');
        $price = $data['price'] ?? null;
        if (!is_numeric($price) || (float)$price < 0) JsonResponse::error('Product price must be a non-negative number.', 422, 'validation_error');
        $slug = slugify((string)($data['slug'] ?? $name));
        if ($slug === '') JsonResponse::error('A valid product slug is required.', 422, 'validation_error');

        $repo = new ProductRepository(Database::connection());
        try {
            (new EntitlementService(Database::connection()))->assertCanCreate((int)$shop['id'], 'products.max', 'products');
            $id = $repo->create((int)$shop['id'], [
                'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
                'name' => $name,
                'slug' => $slug,
                'description' => trim((string)($data['description'] ?? '')),
                'options_json' => json_encode(array_values(array_filter(array_map('trim', (array)($data['options'] ?? [])))), JSON_UNESCAPED_UNICODE),
                'sku' => trim((string)($data['sku'] ?? '')),
                'price' => (float)$price,
                'compare_at_price' => isset($data['compare_at_price']) && $data['compare_at_price'] !== '' ? (float)$data['compare_at_price'] : null,
                'stock_quantity' => max(0, (int)($data['stock_quantity'] ?? 0)),
                'track_inventory' => !empty($data['track_inventory']),
                'image_path' => $data['image_path'] ?? null,
                'status' => in_array(($data['status'] ?? 'draft'), ['draft','active','archived'], true) ? $data['status'] : 'draft',
                'featured' => !empty($data['featured']),
            ]);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 409, 'subscription_limit');
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') JsonResponse::error('A product with that slug already exists in this shop.', 409, 'duplicate_product');
            throw $e;
        }
        JsonResponse::send($repo->find($id, (int)$shop['id']), 201);
    }

    public static function product(int $id): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $product = (new ProductRepository(Database::connection()))->find($id, (int)$shop['id']);
        if (!$product) JsonResponse::error('Product not found.', 404, 'product_not_found');
        JsonResponse::send($product);
    }

    public static function categories(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        JsonResponse::send((new CategoryRepository(Database::connection()))->allForShop((int)$shop['id']));
    }

    public static function createCategory(): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $data = Request::json();
        try {
            (new EntitlementService(Database::connection()))->assertCanCreate((int)$shop['id'], 'categories.max', 'categories');
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 409, 'subscription_limit');
        }
        $name = trim((string)($data['name'] ?? ''));
        $slug = slugify((string)($data['slug'] ?? $name));
        if ($name === '' || $slug === '') JsonResponse::error('Category name is required.', 422, 'validation_error');
        $repo = new CategoryRepository(Database::connection());
        try {
            $id = $repo->create((int)$shop['id'], $name, $slug);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') JsonResponse::error('That category already exists.', 409, 'duplicate_category');
            throw $e;
        }
        JsonResponse::send($repo->findForShop($id, (int)$shop['id']), 201);
    }

    public static function updateProduct(int $id): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $repo = new ProductRepository(Database::connection());
        $existing = $repo->find($id, (int)$shop['id']);
        if (!$existing) JsonResponse::error('Product not found.', 404, 'product_not_found');

        $data = Request::json();
        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = slugify((string)($data['slug'] ?? $existing['slug']));
        $price = $data['price'] ?? $existing['price'];
        if ($name === '' || $slug === '' || !is_numeric($price) || (float)$price < 0) {
            JsonResponse::error('Name, slug and a valid non-negative price are required.', 422, 'validation_error');
        }

        try {
            $repo->update($id, (int)$shop['id'], [
                'category_id' => array_key_exists('category_id', $data) ? ($data['category_id'] ? (int)$data['category_id'] : null) : ($existing['category_id'] ?? null),
                'name' => $name,
                'slug' => $slug,
                'description' => array_key_exists('description', $data) ? trim((string)$data['description']) : $existing['description'],
                'options_json' => array_key_exists('options', $data) ? json_encode(array_values(array_filter(array_map('trim', (array)$data['options']))), JSON_UNESCAPED_UNICODE) : ($existing['options_json'] ?? null),
                'sku' => array_key_exists('sku', $data) ? trim((string)$data['sku']) : $existing['sku'],
                'price' => (float)$price,
                'compare_at_price' => array_key_exists('compare_at_price', $data) && $data['compare_at_price'] !== '' ? (float)$data['compare_at_price'] : ($existing['compare_at_price'] ?? null),
                'stock_quantity' => array_key_exists('stock_quantity', $data) ? max(0, (int)$data['stock_quantity']) : (int)$existing['stock_quantity'],
                'track_inventory' => array_key_exists('track_inventory', $data) ? !empty($data['track_inventory']) : !empty($existing['track_inventory']),
                'image_path' => array_key_exists('image_path', $data) ? $data['image_path'] : $existing['image_path'],
                'status' => in_array(($data['status'] ?? $existing['status']), ['draft','active','archived'], true) ? ($data['status'] ?? $existing['status']) : 'draft',
                'featured' => array_key_exists('featured', $data) ? !empty($data['featured']) : !empty($existing['featured']),
            ]);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') JsonResponse::error('A product with that slug already exists in this shop.', 409, 'duplicate_product');
            throw $e;
        }
        JsonResponse::send($repo->find($id, (int)$shop['id']));
    }

    public static function deleteProduct(int $id): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $repo = new ProductRepository(Database::connection());
        if (!$repo->find($id, (int)$shop['id'])) JsonResponse::error('Product not found.', 404, 'product_not_found');
        $repo->delete($id, (int)$shop['id']);
        JsonResponse::send(['message' => 'Product deleted.']);
    }

    public static function updateCategory(int $id): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $repo = new CategoryRepository(Database::connection());
        $existing = $repo->findForShop($id, (int)$shop['id']);
        if (!$existing) JsonResponse::error('Category not found.', 404, 'category_not_found');
        $data = Request::json();
        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = slugify((string)($data['slug'] ?? $existing['slug']));
        if ($name === '' || $slug === '') JsonResponse::error('Category name is required.', 422, 'validation_error');
        try {
            $repo->update($id, (int)$shop['id'], $name, $slug);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') JsonResponse::error('That category already exists.', 409, 'duplicate_category');
            throw $e;
        }
        JsonResponse::send($repo->findForShop($id, (int)$shop['id']));
    }

    public static function deleteCategory(int $id): never
    {
        $user = ApiAuth::requireUser();
        $shop = ApiAuth::shopForUser((int)$user['id']);
        if (!$shop) JsonResponse::error('Shop not found.', 404, 'shop_not_found');
        $repo = new CategoryRepository(Database::connection());
        if (!$repo->findForShop($id, (int)$shop['id'])) JsonResponse::error('Category not found.', 404, 'category_not_found');
        $repo->delete($id, (int)$shop['id']);
        JsonResponse::send(['message' => 'Category deleted.']);
    }

}
