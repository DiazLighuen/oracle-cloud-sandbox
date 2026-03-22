<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

class AdminMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        if (!$request->getAttribute('user_is_admin', false)) {
            return (new Response())->withHeader('Location', '/unauthorized')->withStatus(302);
        }
        return $handler->handle($request);
    }
}
