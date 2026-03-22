<?php
declare(strict_types=1);

namespace App\Domain\User;

interface UserRepository {
    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    /** @return User[] */
    public function findAll(): array;

    /** Upsert from OAuth flow — intentionally preserves is_admin. */
    public function save(User $user): void;

    /** Admin panel update — explicitly sets name and is_admin. */
    public function update(string $id, ?string $name, bool $isAdmin): void;

    public function delete(string $id): void;

    /**
     * Persist the Google OAuth token JSON for a user.
     * Stores the full token array (access_token, refresh_token, expires_in, created)
     * so the Google Client can later check expiry and auto-refresh.
     */
    public function saveGoogleToken(string $userId, string $tokenJson): void;
}
