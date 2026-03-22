<?php
declare(strict_types=1);

namespace App\Application\Actions\YouTube;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /api/youtube/watched/{video_id}
 * Removes watched status for the authenticated user.
 */
class UnmarkWatchedAction
{
    public function __construct(private PDO $db) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $userId  = $request->getAttribute('user_id');
        $videoId = $args['video_id'];

        $this->db->prepare(
            'DELETE FROM watched_videos WHERE user_id = :uid AND video_id = :vid'
        )->execute([':uid' => $userId, ':vid' => $videoId]);

        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
