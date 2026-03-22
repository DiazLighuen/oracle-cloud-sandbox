<?php
declare(strict_types=1);

namespace App\Application\Actions\Auth;

use Google\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GoogleLoginAction {
    public function __construct(private Client $googleClient) {}

    public function __invoke(Request $request, Response $response): Response {
        $this->googleClient->setPrompt('select_account');
        $authUrl = $this->googleClient->createAuthUrl();

        return $response
            ->withHeader('Location', $authUrl)
            ->withStatus(302);
    }
}