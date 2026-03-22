<?php
declare(strict_types=1);

namespace App\Application\Actions\YouTube;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/youtube/watched
 * Returns all video IDs marked as watched by the authenticated user.
 */
class GetWatchedAction
{
    public function __construct(private PDO $db) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stmt = $this->db->prepare(
            'SELECT video_id FROM watched_videos WHERE user_id = :uid ORDER BY watched_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        $videoIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $response->getBody()->write(json_encode(['video_ids' => $videoIds]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
