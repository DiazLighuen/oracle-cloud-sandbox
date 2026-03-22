<?php
declare(strict_types=1);

namespace App\Application\Actions\YouTube;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PUT /api/youtube/watched/{video_id}
 * Marks a video as watched for the authenticated user (idempotent upsert).
 */
class MarkWatchedAction
{
    public function __construct(private PDO $db) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $userId  = $request->getAttribute('user_id');
        $videoId = $args['video_id'];

        $this->db->prepare(
            'INSERT INTO watched_videos (user_id, video_id)
             VALUES (:uid, :vid)
             ON CONFLICT (user_id, video_id) DO UPDATE SET watched_at = NOW()'
        )->execute([':uid' => $userId, ':vid' => $videoId]);

        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
