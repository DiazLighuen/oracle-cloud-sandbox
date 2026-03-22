<?php
declare(strict_types=1);

namespace App\Infrastructure\I18n;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class TranslatorMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $lang = $request->getCookieParams()['lang'] ?? 'en';
        $translator = new PhpTranslator($lang);
        $request = $request->withAttribute('translator', $translator);
        return $handler->handle($request);
    }
}
