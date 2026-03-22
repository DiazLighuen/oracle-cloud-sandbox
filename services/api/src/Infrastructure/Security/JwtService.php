<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use Firebase\JWT\JWT;
use App\Domain\User\User;

class JwtService {
    public function createToken(User $user): string {
        $payload = [
            'iss'      => 'oracle-sandbox-auth',
            'iat'      => time(),
            'exp'      => time() + (3600 * 24 * 7),
            'sub'      => $user->getId(),
            'email'    => $user->getEmail(),
            'name'     => $user->getName(),
            'avatar'   => $user->getAvatarUrl(),
            'is_admin' => $user->isAdmin(),
        ];

        return JWT::encode($payload, getenv('JWT_SECRET'), 'HS256');
    }
}