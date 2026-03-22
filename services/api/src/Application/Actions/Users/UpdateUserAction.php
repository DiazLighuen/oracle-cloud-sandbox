<?php
declare(strict_types=1);

namespace App\Application\Actions\Users;

use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PATCH /api/users/{id}
 * Body: { "name": "...", "is_admin": true|false }
 * An admin cannot edit their own record to prevent accidental self-lockout.
 */
class UpdateUserAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id     = $args['id'] ?? '';
        $selfId = $request->getAttribute('user_id');

        if ($id === $selfId) {
            return $this->json($response, ['error' => 'You cannot edit your own account'], 403);
        }

        $user = $this->users->findById($id);
        if (!$user) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $body    = (array) ($request->getParsedBody() ?? []);
        $name    = array_key_exists('name', $body)
            ? (trim((string) $body['name']) ?: null)
            : $user->getName();
        $isAdmin = array_key_exists('is_admin', $body)
            ? (bool) $body['is_admin']
            : $user->isAdmin();

        $this->users->update($id, $name, $isAdmin);

        return $this->json($response, ['ok' => true]);
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
