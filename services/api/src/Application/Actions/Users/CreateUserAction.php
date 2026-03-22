<?php
declare(strict_types=1);

namespace App\Application\Actions\Users;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;

/**
 * POST /api/users
 * Body: { "email": "...", "name": "...", "is_admin": false }
 */
class CreateUserAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body    = (array) ($request->getParsedBody() ?? []);
        $email   = strtolower(trim((string) ($body['email'] ?? '')));
        $name    = trim((string) ($body['name'] ?? '')) ?: null;
        $isAdmin = (bool) ($body['is_admin'] ?? false);

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => 'Valid email is required'], 422);
        }

        if ($this->users->findByEmail($email)) {
            return $this->json($response, ['error' => 'A user with that email already exists'], 409);
        }

        $user = new User(Uuid::uuid4()->toString(), $email, $name, null, null, null, $isAdmin);
        $this->users->save($user);

        // If is_admin was requested, apply it explicitly (save() excludes is_admin from upsert)
        if ($isAdmin) {
            $saved = $this->users->findByEmail($email);
            if ($saved) {
                $this->users->update($saved->getId(), $saved->getName(), true);
            }
        }

        return $this->json($response, ['ok' => true, 'email' => $email], 201);
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
