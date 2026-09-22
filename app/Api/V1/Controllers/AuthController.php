<?php
declare(strict_types=1);

namespace App\Api\V1\Controllers;

use App\Api\V1\Support\ApiAuth;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;
use App\Database\Database;

final class AuthController
{
    public static function login(): never
    {
        $data = Request::json();
        $identifier = trim((string)($data['phone'] ?? $data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($identifier === '' || $password === '') {
            JsonResponse::error('Phone/email and password are required.', 422, 'validation_error');
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, phone, email, password_hash, status
             FROM users
             WHERE phone = :identifier OR email = :identifier
             LIMIT 1'
        );
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            JsonResponse::error('The phone/email or password is incorrect.', 401, 'invalid_credentials');
        }

        $expiresAt = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');
        $token = ApiAuth::issue((int)$user['id'], 'Dukame API v1', $expiresAt);
        unset($user['password_hash']);

        JsonResponse::send([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'shop' => ApiAuth::shopForUser((int)$user['id']),
        ], 200);
    }

    public static function me(): never
    {
        $user = ApiAuth::requireUser();
        JsonResponse::send([
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'shop' => ApiAuth::shopForUser((int)$user['id']),
        ]);
    }

    public static function logout(): never
    {
        ApiAuth::requireUser();
        ApiAuth::revokeCurrent();
        JsonResponse::send(['message' => 'Logged out successfully.']);
    }
}
