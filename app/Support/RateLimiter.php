<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    public static function tooMany(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $state = self::load($key);
        $now = time();
        $state['attempts'] = array_values(array_filter(
            $state['attempts'],
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $windowSeconds)
        ));

        if (count($state['attempts']) >= $maxAttempts) {
            self::save($key, $state);
            return true;
        }

        $state['attempts'][] = $now;
        self::save($key, $state);
        return false;
    }

    public static function clear(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function retryAfter(string $key, int $windowSeconds): int
    {
        $state = self::load($key);
        $oldest = $state['attempts'][0] ?? time();
        return max(1, ($oldest + $windowSeconds) - time());
    }

    private static function load(string $key): array
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return ['attempts' => []];
        }

        $handle = fopen($path, 'rb');
        if (!$handle) return ['attempts' => []];
        flock($handle, LOCK_SH);
        $raw = stream_get_contents($handle) ?: '';
        flock($handle, LOCK_UN);
        fclose($handle);

        $data = json_decode($raw, true);
        return is_array($data) && isset($data['attempts']) && is_array($data['attempts'])
            ? $data
            : ['attempts' => []];
    }

    private static function save(string $key, array $state): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/rate-limits';
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }

        $path = self::path($key);
        $handle = fopen($path, 'c+b');
        if (!$handle) return;
        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function path(string $key): string
    {
        return dirname(__DIR__, 2) . '/storage/rate-limits/' . hash('sha256', $key) . '.json';
    }
}
