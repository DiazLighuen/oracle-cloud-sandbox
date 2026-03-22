<?php
declare(strict_types=1);

namespace App\Domain\User;

class User {
    public function __construct(
        private readonly string $id,
        private readonly string $email,
        private ?string $name = null,
        private ?string $googleId = null,
        private ?string $avatarUrl = null,
        private ?string $passwordHash = null,
        private bool    $isAdmin = false,
        private ?string $createdAt = null,
        private ?string $googleToken = null
    ) {}

    public static function createWithGoogle(
        string $uuid,
        string $email,
        string $googleId,
        ?string $name,
        ?string $avatar
    ): self {
        return new self($uuid, $email, $name, $googleId, $avatar);
    }

    // Getters
    public function getId(): string         { return $this->id; }
    public function getEmail(): string      { return $this->email; }
    public function getName(): ?string      { return $this->name; }
    public function getGoogleId(): ?string  { return $this->googleId; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function isAdmin(): bool           { return $this->isAdmin; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }
    public function getGoogleToken(): ?string { return $this->googleToken; }
}
