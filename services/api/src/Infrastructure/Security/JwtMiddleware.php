<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtMiddleware {
    private function jsonError(Response $response, string $message, int $status): Response {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    public function __invoke(Request $request, Handler $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : null;

        if (!$token) {
            $token = $request->getCookieParams()['jwt'] ?? null;
        }

        $isApiRoute = str_starts_with($request->getUri()->getPath(), '/api/');

        if (!$token) {
            return $isApiRoute
                ? $this->jsonError(new Response(), 'Token no proporcionado', 401)
                : (new Response())->withHeader('Location', '/unauthorized')->withStatus(302);
        }

        try {
            $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
            $request = $request
                ->withAttribute('user_id',       $decoded->sub)
                ->withAttribute('user_email',    $decoded->email)
                ->withAttribute('user_name',     $decoded->name ?? null)
                ->withAttribute('user_avatar',   $decoded->avatar ?? null)
                ->withAttribute('user_is_admin', $decoded->is_admin ?? false);
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $isApiRoute
                ? $this->jsonError(new Response(), 'Token inválido o expirado', 401)
                : (new Response())->withHeader('Location', '/unauthorized')->withStatus(302);
        }
    }
}
