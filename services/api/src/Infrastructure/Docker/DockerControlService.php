<?php
declare(strict_types=1);

namespace App\Infrastructure\Docker;

class DockerControlService
{
    private const SOCKET = '/var/run/docker.sock';

    /**
     * Containers that must never be stopped via the API.
     * Stopping nginx or the api itself would make the service unreachable;
     * stopping db risks data corruption; certbot manages TLS certificates.
     */
    public const PROTECTED = ['php_app', 'nginx_server', 'postgres_db', 'certbot'];

    /**
     * Maps container names to logical module identifiers.
     * Used to derive which features are available based on running state.
     */
    public const MODULE_MAP = [
        'youtube_svc'       => 'youtube',
        'notifications_svc' => 'notifications',
    ];

    public function isControllable(string $containerName): bool
    {
        return !in_array($containerName, self::PROTECTED, true);
    }

    /**
     * Returns the running state of each logical module.
     * Fails open: if the Docker socket is unavailable, all modules are assumed running
     * so UI elements are never hidden due to a socket/permissions issue.
     *
     * @return array<string, bool>  e.g. ['youtube' => true, 'notifications' => false]
     */
    public function getModuleStates(): array
    {
        $states = array_fill_keys(array_values(self::MODULE_MAP), true);

        if (!file_exists(self::SOCKET)) {
            return $states;
        }

        $containers = $this->listContainers();
        if (!is_array($containers)) {
            return $states;
        }

        foreach ($containers as $c) {
            $name = ltrim($c['Names'][0] ?? '', '/');
            if (isset(self::MODULE_MAP[$name])) {
                $states[self::MODULE_MAP[$name]] = ($c['State'] ?? '') === 'running';
            }
        }

        return $states;
    }

    private function listContainers(): mixed
    {
        $ch = curl_init('http://localhost/containers/json?all=1');
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => self::SOCKET,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_TIMEOUT          => 5,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $raw, true);
    }

    /**
     * Start a container by name.
     *
     * @return array{success: bool, error?: string}
     */
    public function start(string $containerName): array
    {
        return $this->action($containerName, 'start');
    }

    /**
     * Stop a container by name.
     *
     * @return array{success: bool, error?: string}
     */
    public function stop(string $containerName): array
    {
        return $this->action($containerName, 'stop');
    }

    private function action(string $containerName, string $action): array
    {
        if (!file_exists(self::SOCKET)) {
            return ['success' => false, 'error' => 'docker_unavailable'];
        }

        $ch = curl_init("http://localhost/containers/{$containerName}/{$action}");
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => self::SOCKET,
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => '',
            CURLOPT_TIMEOUT          => 15,
        ]);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 204 = success, 304 = already in desired state (treat as success)
        if ($httpCode === 204 || $httpCode === 304) {
            return ['success' => true];
        }

        if ($httpCode === 404) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => false, 'error' => 'docker_error'];
    }
}
