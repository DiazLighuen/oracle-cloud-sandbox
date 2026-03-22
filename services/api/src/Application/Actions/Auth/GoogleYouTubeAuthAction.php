<?php
declare(strict_types=1);

namespace App\Application\Actions\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Initiates an incremental Google OAuth flow to obtain the youtube
 * scope for the currently logged-in user.
 *
 * Uses a separate redirect URI (GOOGLE_YOUTUBE_REDIRECT_URL) so the consent
 * screen and callback are completely independent from the main login flow.
 * The user must be authenticated (JwtMiddleware) before reaching this action.
 */
class GoogleYouTubeAuthAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $client = new \Google\Client();
        $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(getenv('GOOGLE_YOUTUBE_REDIRECT_URL'));
        $client->addScope('https://www.googleapis.com/auth/youtube');
        $client->setAccessType('offline');
        // prompt=consent ensures Google always returns a refresh_token,
        // even if the user has previously authorized this scope.
        $client->setPrompt('consent');

        return $response
            ->withHeader('Location', $client->createAuthUrl())
            ->withStatus(302);
    }
}
