<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use App\Infrastructure\Persistence\PostgreSqlUserRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Auth\DbWhitelistService;
use App\Domain\Auth\WhitelistService;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // Database
    PDO::class => function () {
        $dsn = sprintf("pgsql:host=%s;port=5432;dbname=%s;", getenv('DB_HOST'), getenv('POSTGRES_DB'));
        return new PDO($dsn, getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    },

    // Interfaces
    UserRepository::class => \DI\autowire(PostgreSqlUserRepository::class),
    WhitelistService::class => \DI\autowire(DbWhitelistService::class),
    
    // Google — login client (email + profile only)
    Google\Client::class => function () {
        $client = new Google\Client();
        $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URL'));
        $client->addScope("email");
        $client->addScope("profile");
        return $client;
    },
]);

return $containerBuilder->build();