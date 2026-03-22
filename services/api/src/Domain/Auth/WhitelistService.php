<?php
declare(strict_types=1);

namespace App\Domain\Auth;

interface WhitelistService
{
    public function isAllowed(string $email): bool;
}
