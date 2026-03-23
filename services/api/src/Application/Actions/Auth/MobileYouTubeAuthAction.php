<?php
declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /auth/youtube/mobile
 *
 * Mobile equivalent of GoogleYouTubeCallbackAction.
 * The iOS app performs the Google OAuth flow (youtube.readonly scope) and
 * obtains a serverAuthCode; this endpoint exchanges it for tokens and
 * persists them so the Python youtube service can call the YouTube Data API.
 *
 * Request body (JSON):
 *   { "server_auth_code": "<code from iOS GIDSignIn>" }
 *
 * Response (JSON):
 *   200 { "ok": true }
 *   400 { "error": "server_auth_code is required" }
 *   500 { "error": "..." }
 *
 * Auth: JWT Bearer (JwtMiddleware injects user_id as request attribute).
 */
class MobileYouTubeAuthAction
{
    public function __construct(private UserRepository $userRepository) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $body           = (array) ($request->getParsedBody() ?? []);
        $serverAuthCode = trim((string) ($body['server_auth_code'] ?? ''));

        if (!$serverAuthCode) {
            return $this->json($response, ['error' => 'server_auth_code is required'], 400);
        }

        try {
            // Exchange the server auth code using the web client credentials.
            // iOS GIDSignIn generates this code when serverClientID = web client ID.
            // The redirect URI must be empty for server-side code exchange.
            $client = new \Google\Client();
            $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
            $client->setRedirectUri('');

            $tokenArray = $client->fetchAccessTokenWithAuthCode($serverAuthCode);

            if (isset($tokenArray['error'])) {
                return $this->json($response, ['error' => 'Failed to exchange auth code: ' . $tokenArray['error']], 500);
            }

            // Preserve existing refresh_token if Google didn't return a new one.
            if (empty($tokenArray['refresh_token'])) {
                $existing = $this->userRepository->findById($userId);
                if ($existing && $existing->getGoogleToken()) {
                    $stored = json_decode($existing->getGoogleToken(), true);
                    if (!empty($stored['refresh_token'])) {
                        $tokenArray['refresh_token'] = $stored['refresh_token'];
                    }
                }
            }

            $this->userRepository->saveGoogleToken($userId, json_encode($tokenArray));

            return $this->json($response, ['ok' => true]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
