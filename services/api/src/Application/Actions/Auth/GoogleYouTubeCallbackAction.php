<?php
declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\User\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles the OAuth callback from Google for the YouTube scope.
 * Exchanges the code for a token, saves it to the DB, and redirects
 * back to /youtube.
 *
 * The user must have an active JWT cookie so we know which account to
 * associate the token with. If the cookie is absent or invalid we
 * redirect to /unauthorized.
 */
class GoogleYouTubeCallbackAction
{
    public function __construct(private UserRepository $userRepository) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Identify the logged-in user from the JWT cookie
        $jwt = $request->getCookieParams()['jwt'] ?? null;
        if (!$jwt) {
            return $response->withHeader('Location', '/unauthorized')->withStatus(302);
        }

        try {
            $decoded = JWT::decode($jwt, new Key(getenv('JWT_SECRET'), 'HS256'));
            $userId  = $decoded->sub;
        } catch (\Exception) {
            return $response->withHeader('Location', '/unauthorized')->withStatus(302);
        }

        // 2. Check for OAuth error (e.g. user denied consent)
        $params = $request->getQueryParams();
        if (!empty($params['error'])) {
            return $response->withHeader('Location', '/youtube?yt_auth=denied')->withStatus(302);
        }

        $code = $params['code'] ?? null;
        if (!$code) {
            return $response->withHeader('Location', '/youtube?yt_auth=error')->withStatus(302);
        }

        // 3. Exchange the code for a token using the YouTube-specific redirect URI
        $client = new \Google\Client();
        $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(getenv('GOOGLE_YOUTUBE_REDIRECT_URL'));

        $tokenArray = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($tokenArray['error'])) {
            return $response->withHeader('Location', '/youtube?yt_auth=error')->withStatus(302);
        }

        // 4. Preserve existing refresh_token if Google didn't return a new one
        if (empty($tokenArray['refresh_token'])) {
            $existing = $this->userRepository->findById($userId);
            if ($existing && $existing->getGoogleToken()) {
                $stored = json_decode($existing->getGoogleToken(), true);
                if (!empty($stored['refresh_token'])) {
                    $tokenArray['refresh_token'] = $stored['refresh_token'];
                }
            }
        }

        // 5. Persist the token
        $this->userRepository->saveGoogleToken($userId, json_encode($tokenArray));

        return $response->withHeader('Location', '/youtube?yt_auth=ok')->withStatus(302);
    }
}
