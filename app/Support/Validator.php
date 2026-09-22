<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    public static function required(string $value, string $field): ?string
    {
        if (trim($value) === '') {
            return "{$field} is required.";
        }

        return null;
    }

    public static function phone(string $value): ?string
    {
        $normalized = preg_replace('/\s+/', '', $value);

        if (!preg_match('/^(?:2547\d{8}|07\d{8}|01\d{8})$/', $normalized)) {
            return 'Enter a valid Kenyan phone number.';
        }

        return null;
    }

    public static function password(string $value): ?string
    {
        if (strlen($value) < 8) {
            return 'Password must be at least 8 characters.';
        }

        return null;
    }
}
