<?php
declare(strict_types=1);

namespace App\Application\Actions\Auth;

use Google\Client;
use App\Domain\Auth\WhitelistService;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Infrastructure\Security\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /auth/google/mobile
 *
 * Mobile auth endpoint for the Swift app.
 * Receives a Google ID token (from the iOS GoogleSignIn SDK),
 * validates it, upserts the user, and returns a JWT in the response body.
 *
 * Unlike the web OAuth flow, no cookie is set — the mobile client
 * is responsible for storing and sending the token.
 *
 * Request body (JSON):
 *   { "id_token": "<Google ID token>" }
 *
 * Response (JSON):
 *   200 { "token": "<JWT>", "user": { "name": "...", "email": "...", "avatar": "..." } }
 *   401 { "error": "Email not authorized" }
 *   422 { "error": "id_token is required" }
 *   500 { "error": "..." }
 */
class MobileAuthAction
{
    public function __construct(
        private Client $googleClient,
        private UserRepository $userRepository,
        private JwtService $jwtService,
        private WhitelistService $whitelistService,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body    = (array) ($request->getParsedBody() ?? []);
        $idToken = trim((string) ($body['id_token'] ?? ''));

        if (!$idToken) {
            return $this->json($response, ['error' => 'id_token is required'], 422);
        }

        try {
            // 1. Verify the Google ID token.
            // iOS tokens carry aud = iOS client ID, not the web client ID.
            // setClientId() overrides the audience used by verifyIdToken().
            $iosClientId = getenv('GOOGLE_IOS_CLIENT_ID');
            if ($iosClientId) {
                $this->googleClient->setClientId($iosClientId);
            }
            $payload = $this->googleClient->verifyIdToken($idToken);
            if (!$payload) {
                return $this->json($response, ['error' => 'Invalid Google token'], 401);
            }

            $email    = strtolower(trim((string) ($payload['email'] ?? '')));
            $name     = (string) ($payload['name'] ?? '');
            $picture  = (string) ($payload['picture'] ?? '');
            $googleId = (string) ($payload['sub'] ?? '');

            if (!$email) {
                return $this->json($response, ['error' => 'Could not read email from token'], 422);
            }

            // 2. Check whitelist
            if (!$this->whitelistService->isAllowed($email)) {
                return $this->json($response, ['error' => 'Email not authorized'], 401);
            }

            // 3. Upsert user (preserves is_admin, same as web flow)
            $existing = $this->userRepository->findByEmail($email);
            $user     = User::createWithGoogle(
                $existing->getId(),
                $existing->getEmail(),
                $googleId,
                $name,
                $picture,
            );
            $this->userRepository->save($user);

            // 4. Re-read from DB so is_admin is accurate in the JWT
            $savedUser = $this->userRepository->findByEmail($email) ?? $user;

            // 5. Issue JWT — same secret, same format as web flow
            $jwt = $this->jwtService->createToken($savedUser);

            return $this->json($response, [
                'token' => $jwt,
                'user'  => [
                    'name'     => $savedUser->getName(),
                    'email'    => $savedUser->getEmail(),
                    'avatar'   => $savedUser->getAvatarUrl(),
                    'is_admin' => $savedUser->isAdmin(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => 'Authentication error: ' . $e->getMessage()], 500);
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
