<?php
declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Auth\WhitelistService;
use PDO;

class DbWhitelistService implements WhitelistService
{
    public function __construct(private PDO $connection) {}

    public function isAllowed(string $email): bool
    {
        $stmt = $this->connection->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);
        return $stmt->fetchColumn() !== false;
    }
}
