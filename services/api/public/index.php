<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Application\Actions\Auth\GoogleLoginAction;
use App\Application\Actions\Auth\GoogleCallbackAction;
use App\Application\Actions\Auth\MobileAuthAction;
use App\Application\Actions\Auth\GoogleYouTubeAuthAction;
use App\Application\Actions\Auth\GoogleYouTubeCallbackAction;
use App\Application\Actions\Dashboard\DashboardAction;
use App\Application\Actions\Dashboard\GetContainersAction;
use App\Application\Actions\Dashboard\YouTubeAction;
use App\Application\Actions\Users\UsersAction;
use App\Application\Actions\Users\GetUsersAction;
use App\Application\Actions\Users\CreateUserAction;
use App\Application\Actions\Users\UpdateUserAction;
use App\Application\Actions\Users\DeleteUserAction;
use App\Application\Actions\Notifications\WsTestAction;
use App\Application\Actions\Landing\LandingAction;
use App\Application\Actions\Error\UnauthorizedAction;
use App\Infrastructure\Security\AdminMiddleware;
use App\Infrastructure\Security\JwtMiddleware;
use App\Infrastructure\I18n\TranslatorMiddleware;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$container = require __DIR__ . '/../config/container.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();
$app->add(TranslatorMiddleware::class);

$app->get('/lang/{code}', function ($request, $response, $args) {
    $code = in_array($args['code'], ['en', 'es', 'de'], true) ? $args['code'] : 'en';
    $referer = $request->getHeaderLine('Referer') ?: '/';
    return $response
        ->withHeader('Set-Cookie', "lang={$code}; Path=/; Max-Age=31536000")
        ->withHeader('Location', $referer)
        ->withStatus(302);
});

$app->get('/', LandingAction::class);
$app->get('/unauthorized', UnauthorizedAction::class);

$app->group('/auth', function ($group) {
    $group->get('/google', GoogleLoginAction::class);
    $group->get('/google/callback', GoogleCallbackAction::class);
    $group->post('/google/mobile', MobileAuthAction::class);
    $group->get('/logout', function ($request, $response) {
        return $response
            ->withHeader('Set-Cookie', 'jwt=; HttpOnly; Path=/; Max-Age=0')
            ->withHeader('Location', '/')
            ->withStatus(302);
    });
});

// Incremental YouTube authorization — /auth/youtube requires an active session,
// the callback is public (Google redirects back after consent).
$app->get('/auth/youtube', GoogleYouTubeAuthAction::class)->add(JwtMiddleware::class);
$app->get('/auth/youtube/callback', GoogleYouTubeCallbackAction::class);

$app->get('/dashboard', DashboardAction::class)->add(AdminMiddleware::class)->add(JwtMiddleware::class);
$app->get('/users', UsersAction::class)->add(AdminMiddleware::class)->add(JwtMiddleware::class);
$app->get('/ws-test', WsTestAction::class)->add(AdminMiddleware::class)->add(JwtMiddleware::class);
$app->get('/youtube', YouTubeAction::class)->add(JwtMiddleware::class);

// NOTE: /api/youtube/* is proxied by nginx to the Python youtube service.
// User-specific data (watched history) lives here under /api/user/* instead.
$app->group('/api/user', function ($group) {
    $group->get('/watched', \App\Application\Actions\YouTube\GetWatchedAction::class);
    $group->put('/watched/{video_id}', \App\Application\Actions\YouTube\MarkWatchedAction::class);
    $group->delete('/watched/{video_id}', \App\Application\Actions\YouTube\UnmarkWatchedAction::class);
})->add(JwtMiddleware::class);

$app->group('/api', function ($group) {
    $group->get('/dashboard', \App\Application\Actions\Dashboard\GetDashboardAction::class);
    $group->get('/containers', GetContainersAction::class);
    $group->get('/users', GetUsersAction::class);
    $group->post('/users', CreateUserAction::class);
    $group->patch('/users/{id}', UpdateUserAction::class);
    $group->delete('/users/{id}', DeleteUserAction::class);
})->add(AdminMiddleware::class)->add(JwtMiddleware::class);

$app->run();