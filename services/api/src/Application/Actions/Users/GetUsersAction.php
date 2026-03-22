<?php
declare(strict_types=1);

namespace App\Application\Actions\Users;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/users
 * Returns all users as a JSON array.
 * Requires admin JWT.
 */
class GetUsersAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $users = array_map(fn(User $u) => [
            'id'         => $u->getId(),
            'email'      => $u->getEmail(),
            'name'       => $u->getName(),
            'avatar'     => $u->getAvatarUrl(),
            'is_admin'   => $u->isAdmin(),
            'created_at' => $u->getCreatedAt(),
        ], $this->users->findAll());

        $response->getBody()->write(json_encode($users));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
