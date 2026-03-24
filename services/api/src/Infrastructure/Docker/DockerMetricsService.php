<?php
declare(strict_types=1);

namespace App\Infrastructure\Docker;

class DockerMetricsService
{
    private const SOCKET     = '/var/run/docker.sock';
    private const CACHE_FILE = '/tmp/docker_metrics_cache.json';
    private const CACHE_TTL  = 10; // seconds — keeps CPU cost near zero on the free tier

    /** @return array{available: bool, containers: array} */
    public function getMetrics(): array
    {
        // Serve from cache if fresh enough
        if (file_exists(self::CACHE_FILE)) {
            $age = time() - (int) filemtime(self::CACHE_FILE);
            if ($age < self::CACHE_TTL) {
                $cached = json_decode((string) file_get_contents(self::CACHE_FILE), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        if (!file_exists(self::SOCKET)) {
            return ['available' => false, 'containers' => []];
        }

        $containers = $this->request('/containers/json?all=1');
        if (!is_array($containers)) {
            return ['available' => false, 'containers' => []];
        }

        $result = [];
        foreach ($containers as $c) {
            $stats = $this->request("/containers/{$c['Id']}/stats?stream=false&one-shot=true");
            if (!is_array($stats)) {
                continue;
            }
            $result[] = $this->parse($c, $stats);
        }

        usort($result, fn($a, $b) => $b['running'] <=> $a['running']);

        $data = ['available' => true, 'containers' => $result];

        // Persist cache (best-effort, ignore write errors)
        @file_put_contents(self::CACHE_FILE, json_encode($data));

        return $data;
    }

    private function request(string $path): mixed
    {
        $ch = curl_init("http://localhost{$path}");
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => self::SOCKET,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_TIMEOUT          => 8,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $raw, true);
    }

    private function parse(array $c, array $s): array
    {
        // ── CPU ────────────────────────────────────────────────────────────
        $cpuDelta    = ($s['cpu_stats']['cpu_usage']['total_usage']    ?? 0)
                     - ($s['precpu_stats']['cpu_usage']['total_usage'] ?? 0);
        $systemDelta = ($s['cpu_stats']['system_cpu_usage']            ?? 0)
                     - ($s['precpu_stats']['system_cpu_usage']         ?? 0);
        $cpus        = $s['cpu_stats']['online_cpus'] ?? 1;
        $cpuPct      = $systemDelta > 0 ? round(($cpuDelta / $systemDelta) * $cpus * 100, 1) : 0.0;

        // ── Memory ─────────────────────────────────────────────────────────
        $memUsage   = $s['memory_stats']['usage'] ?? 0;
        // cgroup v2 uses 'inactive_file'; cgroup v1 uses 'cache'. Subtract
        // whichever is present to get an RSS-like value (matching docker stats).
        $stats      = $s['memory_stats']['stats'] ?? [];
        $memCache   = $stats['inactive_file'] ?? $stats['cache'] ?? 0;
        $memRss     = max(0, $memUsage - $memCache);
        $memLimit   = $s['memory_stats']['limit'] ?? 1;
        $memPct     = $memLimit > 0 ? round(($memRss / $memLimit) * 100, 1) : 0.0;

        // ── Network ────────────────────────────────────────────────────────
        $netRx = $netTx = 0;
        foreach ($s['networks'] ?? [] as $net) {
            $netRx += $net['rx_bytes']   ?? 0;
            $netTx += $net['tx_bytes']   ?? 0;
        }

        // ── Block I/O ──────────────────────────────────────────────────────
        $blkRead = $blkWrite = 0;
        foreach ($s['blkio_stats']['io_service_bytes_recursive'] ?? [] as $io) {
            if (($io['op'] ?? '') === 'Read')  $blkRead  += $io['value'] ?? 0;
            if (($io['op'] ?? '') === 'Write') $blkWrite += $io['value'] ?? 0;
        }

        // ── PIDs ───────────────────────────────────────────────────────────
        $pids = $s['pids_stats']['current'] ?? 0;

        // ── Container info ─────────────────────────────────────────────────
        $running = ($c['State'] ?? '') === 'running';

        return [
            'id'         => substr($c['Id'] ?? '', 0, 12),
            'name'       => ltrim($c['Names'][0] ?? 'unknown', '/'),
            'image'      => $c['Image'] ?? '?',
            'status'     => $c['Status'] ?? '?',
            'running'    => $running,
            'cpu_pct'    => $cpuPct,
            'mem_rss'    => $memRss,
            'mem_limit'  => $memLimit,
            'mem_pct'    => $memPct,
            'net_rx'     => $netRx,
            'net_tx'     => $netTx,
            'blk_read'   => $blkRead,
            'blk_write'  => $blkWrite,
            'pids'       => $pids,
        ];
    }

    public static function fmt(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 2) . ' GB',
            $bytes >= 1_048_576     => round($bytes / 1_048_576, 1)     . ' MB',
            $bytes >= 1_024         => round($bytes / 1_024, 1)         . ' KB',
            default                 => $bytes . ' B',
        };
    }
}
