<?php
declare(strict_types=1);

namespace App\Application\Actions\Dashboard;

use App\Infrastructure\Docker\DockerControlService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/containers/{name}/start
 * POST /api/containers/{name}/stop
 *
 * Starts or stops a Docker container by name.
 * Protected containers (nginx, api, db, certbot) always return 403.
 * Requires admin JWT.
 *
 * Response 200: { "success": true, "name": "youtube_svc", "action": "start" }
 * Response 400: { "error": "invalid_action" }
 * Response 403: { "error": "container_protected" }
 * Response 404: { "error": "not_found" }
 * Response 503: { "error": "docker_unavailable" }
 */
class ToggleContainerAction
{
    public function __construct(private DockerControlService $docker) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $name   = $args['name']   ?? '';
        $action = $args['action'] ?? '';

        if (!in_array($action, ['start', 'stop'], true)) {
            $response->getBody()->write(json_encode(['error' => 'invalid_action']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!$this->docker->isControllable($name)) {
            $response->getBody()->write(json_encode(['error' => 'container_protected']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $result = $action === 'start'
            ? $this->docker->start($name)
            : $this->docker->stop($name);

        if (!$result['success']) {
            $error  = $result['error'] ?? 'docker_error';
            $status = match ($error) {
                'not_found'          => 404,
                'docker_unavailable' => 503,
                default              => 500,
            };
            $response->getBody()->write(json_encode(['error' => $error]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'name'    => $name,
            'action'  => $action,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
