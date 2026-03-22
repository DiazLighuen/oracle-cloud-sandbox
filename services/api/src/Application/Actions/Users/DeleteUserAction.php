<?php
declare(strict_types=1);

namespace App\Application\Actions\Users;

use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /api/users/{id}
 * An admin cannot delete their own account.
 */
class DeleteUserAction
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id     = $args['id'] ?? '';
        $selfId = $request->getAttribute('user_id');

        if ($id === $selfId) {
            return $this->json($response, ['error' => 'You cannot delete your own account'], 403);
        }

        $user = $this->users->findById($id);
        if (!$user) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $this->users->delete($id);

        return $this->json($response, ['ok' => true]);
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
