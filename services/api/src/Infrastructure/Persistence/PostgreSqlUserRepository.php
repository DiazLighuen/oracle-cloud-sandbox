<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use PDO;

class PostgreSqlUserRepository implements UserRepository {
    public function __construct(private PDO $connection) {}

    public function findByEmail(string $email): ?User {
        $stmt = $this->connection->prepare(
            'SELECT id, email, name, google_id, avatar_url, is_admin, created_at, google_token FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['email'],
            $data['name'],
            $data['google_id'],
            $data['avatar_url'],
            null,
            (bool) $data['is_admin'],
            $data['created_at'],
            $data['google_token']
        );
    }

    public function findAll(): array {
        $stmt = $this->connection->query(
            'SELECT id, email, name, avatar_url, is_admin, created_at FROM users ORDER BY is_admin DESC, created_at ASC'
        );
        return array_map(
            fn($d) => new User(
                $d['id'],
                $d['email'],
                $d['name'],
                null,
                $d['avatar_url'],
                null,
                (bool) $d['is_admin'],
                $d['created_at']
            ),
            $stmt->fetchAll()
        );
    }

    public function findById(string $id): ?User {
        $stmt = $this->connection->prepare(
            'SELECT id, email, name, google_id, avatar_url, is_admin, created_at, google_token FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        if (!$data) return null;

        return new User(
            $data['id'], $data['email'], $data['name'], $data['google_id'],
            $data['avatar_url'], null, (bool) $data['is_admin'], $data['created_at'],
            $data['google_token']
        );
    }

    public function saveGoogleToken(string $userId, string $tokenJson): void {
        $stmt = $this->connection->prepare(
            'UPDATE users SET google_token = :token, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'token' => $tokenJson]);
    }

    public function update(string $id, ?string $name, bool $isAdmin): void {
        $stmt = $this->connection->prepare(
            'UPDATE users SET name = :name, is_admin = :is_admin, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'name' => $name, 'is_admin' => $isAdmin]);
    }

    public function delete(string $id): void {
        $stmt = $this->connection->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function save(User $user): void {
        // is_admin is intentionally excluded from ON CONFLICT UPDATE so that
        // admin status set via CLI is preserved when the user logs in again.
        $sql = "INSERT INTO users (id, email, name, google_id, avatar_url)
                VALUES (:id, :email, :name, :google_id, :avatar_url)
                ON CONFLICT (email) DO UPDATE
                SET name       = EXCLUDED.name,
                    avatar_url = EXCLUDED.avatar_url,
                    google_id  = EXCLUDED.google_id,
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'id'         => $user->getId(),
            'email'      => $user->getEmail(),
            'name'       => $user->getName(),
            'google_id'  => $user->getGoogleId(),
            'avatar_url' => $user->getAvatarUrl(),
        ]);
    }
}
