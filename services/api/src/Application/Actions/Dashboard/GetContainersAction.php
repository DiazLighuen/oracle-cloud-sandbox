<?php
declare(strict_types=1);

namespace App\Application\Actions\Dashboard;

use App\Infrastructure\Docker\DockerMetricsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /api/containers
 * Returns live Docker container metrics as JSON.
 * Requires admin JWT.
 *
 * Response 200:
 * {
 *   "available": true,
 *   "containers": [
 *     {
 *       "id":        "abc123def456",   // short container ID (12 chars)
 *       "name":      "php_app",
 *       "image":     "myapp:latest",
 *       "status":    "Up 3 hours",
 *       "running":   true,
 *       "cpu_pct":   1.4,              // % across all CPUs
 *       "mem_rss":   52428800,         // bytes (RSS, excluding file cache)
 *       "mem_limit": 268435456,        // bytes (container memory limit)
 *       "mem_pct":   19.5,             // %
 *       "net_rx":    1048576,          // bytes received (cumulative)
 *       "net_tx":    524288,           // bytes sent (cumulative)
 *       "blk_read":  2097152,          // bytes read from disk (cumulative)
 *       "blk_write": 1048576,          // bytes written to disk (cumulative)
 *       "pids":      8                 // process count
 *     }
 *   ]
 * }
 *
 * If the Docker socket is unavailable (e.g. not mounted):
 * { "available": false, "containers": [] }
 */
class GetContainersAction
{
    public function __construct(private DockerMetricsService $docker) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $this->docker->getMetrics();
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
