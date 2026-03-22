<?php
declare(strict_types=1);

namespace App\Application\Actions\Dashboard;

use App\Infrastructure\Docker\DockerMetricsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/dashboard
 * Returns aggregated dashboard data: Docker container metrics + YouTube API quota.
 * Requires admin JWT.
 *
 * Response 200:
 * {
 *   "containers": {
 *     "available": true,
 *     "items": [ { ...container metrics... } ]
 *   },
 *   "youtube_quota": {
 *     "used": 142,
 *     "limit": 10000,
 *     "remaining": 9858,
 *     "percent": 1.4,
 *     "reset_date": "2026-03-21"
 *   }
 * }
 *
 * youtube_quota is null if the YouTube service is unreachable.
 */
class GetDashboardAction
{
    public function __construct(private DockerMetricsService $docker) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $metrics = $this->docker->getMetrics();

        $quota = null;
        $ctx   = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        $raw   = @file_get_contents('http://youtube_svc:8000/quota', false, $ctx);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['used'])) {
                $quota = $decoded;
            }
        }

        $payload = [
            'containers'    => [
                'available' => $metrics['available'],
                'items'     => $metrics['containers'],
            ],
            'youtube_quota' => $quota,
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
