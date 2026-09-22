<?php

declare(strict_types=1);

namespace App\Support;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');

    return $base . '/' . ltrim($path, '/');
}


function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = dirname(__DIR__, 2) . '/public' . $path;
    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
}


function slugify(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '';
    $value = strtolower(trim($value, '-'));

    return $value;
}

function route_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function shop_url(string $slug): string
{
    return route_url($slug);
}

function reserved_shop_slug(string $slug): bool
{
    static $reserved = [
        'login', 'logout', 'register', 'onboarding', 'dashboard',
        'products', 'categories', 'settings', 'cart', 'checkout', 'track',
        'account', 'orders', 'customers', 'help', 'about', 'contact',
        'api', 'admin', 'assets', 'uploads', 'shop', 'favicon', 'robots',
        'sitemap', 'terms', 'privacy', 'support',
    ];

    return in_array(strtolower(trim($slug)), $reserved, true);
}


function store_product_image(array $file, int $shopId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Image upload failed.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new \RuntimeException('Product images must be 5MB or smaller.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new \RuntimeException('Use a JPG, PNG or WebP image.');
    }

    $directory = dirname(__DIR__, 2) . '/public/uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new \RuntimeException('Could not create the image directory.');
    }

    $filename = 'shop-' . $shopId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) {
        throw new \RuntimeException('Could not save the product image.');
    }

    return '/uploads/products/' . $filename;
}

function app_key(): string
{
    $key = trim((string)($_ENV['APP_KEY'] ?? ''));
    if ($key === '') {
        throw new \RuntimeException('APP_KEY is required to save M-Pesa credentials securely.');
    }

    $decoded = base64_decode($key, true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new \RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
    }

    return $decoded;
}

function encrypt_secret(?string $value): ?string
{
    $value = $value !== null ? trim($value) : '';
    if ($value === '') {
        return null;
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new \RuntimeException('Could not encrypt a sensitive setting.');
    }

    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? null : $plain;
}

function store_shop_logo(array $file, int $shopId, ?string $oldPath = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Logo upload failed.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new \RuntimeException('Shop logos must be 2MB or smaller.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new \RuntimeException('Use a JPG, PNG or WebP logo.');
    }

    $directory = dirname(__DIR__, 2) . '/public/uploads/shops';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new \RuntimeException('Could not create the shop logo directory.');
    }

    $filename = 'shop-' . $shopId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $path = $directory . '/' . $filename;
    if (!move_uploaded_file($tmp, $path)) {
        throw new \RuntimeException('Could not save the shop logo.');
    }

    if ($oldPath && str_starts_with($oldPath, '/uploads/shops/')) {
        $oldFile = dirname(__DIR__, 2) . '/public' . $oldPath;
        if (is_file($oldFile)) { @unlink($oldFile); }
    }

    return '/uploads/shops/' . $filename;
}
