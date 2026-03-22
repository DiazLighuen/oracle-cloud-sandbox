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

class GoogleCallbackAction {
    public function __construct(
        private Client $googleClient,
        private UserRepository $userRepository,
        private JwtService $jwtService,
        private WhitelistService $whitelistService
    ) {}

    public function __invoke(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? null;

        if (!$code) {
            return $this->errorResponse($response, 'Código de autorización no encontrado', 400);
        }

        try {
            // 1. Intercambiar el código por un token de acceso
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($code);
            $this->googleClient->setAccessToken($token);

            // 2. Obtener los datos del perfil desde Google
            $googleService = new \Google\Service\Oauth2($this->googleClient);
            $userData = $googleService->userinfo->get();

            // 3. Verificar si el email está autorizado (debe existir en users)
            if (!$this->whitelistService->isAllowed($userData->email)) {
                return $response->withHeader('Location', '/unauthorized')->withStatus(302);
            }

            // 4. Obtener usuario y actualizar sus datos de perfil con los de Google
            $existing = $this->userRepository->findByEmail($userData->email);
            $user = User::createWithGoogle(
                $existing->getId(),
                $existing->getEmail(),
                $userData->id,
                $userData->name,
                $userData->picture
            );
            $this->userRepository->save($user);

            // 5. Re-leer el usuario desde la DB para obtener el is_admin real,
            //    ya que createWithGoogle() siempre tiene is_admin = false.
            $savedUser = $this->userRepository->findByEmail($userData->email) ?? $user;

            // 6. Generar JWT y setearlo como cookie httpOnly
            // Note: google_token (YouTube OAuth) is intentionally NOT touched here.
            // It is only written by GoogleYouTubeCallbackAction so that re-login
            // does not overwrite the youtube.readonly-scoped token.
            $jwt = $this->jwtService->createToken($savedUser);
            $cookie = sprintf(
                'jwt=%s; HttpOnly; Path=/; SameSite=Lax; Max-Age=604800',
                $jwt
            );

            $destination = $savedUser->isAdmin() ? '/dashboard' : '/youtube';
            return $response
                ->withHeader('Set-Cookie', $cookie)
                ->withHeader('Location', $destination)
                ->withStatus(302);

        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Error en la autenticación: ' . $e->getMessage(), 500);
        }
    }

    private function errorResponse(Response $response, string $message, int $status): Response {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}