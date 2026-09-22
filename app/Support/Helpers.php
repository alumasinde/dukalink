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
