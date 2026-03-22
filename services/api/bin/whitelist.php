#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

$dsn = sprintf(
    'pgsql:host=%s;port=5432;dbname=%s;',
    getenv('DB_HOST') ?: 'localhost',
    getenv('POSTGRES_DB')
);

try {
    $pdo = new PDO($dsn, getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    echo "Error conectando a la DB: " . $e->getMessage() . "\n";
    exit(1);
}

$command = $argv[1] ?? null;
$email   = isset($argv[2]) ? strtolower(trim($argv[2])) : null;

switch ($command) {
    case 'add':
        if (!$email) {
            echo "Uso: whitelist.php add <email>\n";
            exit(1);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, email) VALUES (:id, :email) ON CONFLICT (email) DO NOTHING'
        );
        $stmt->execute(['id' => Uuid::uuid4()->toString(), 'email' => $email]);
        if ($stmt->rowCount() > 0) {
            echo "OK: $email agregado.\n";
        } else {
            echo "WARN: $email ya existe.\n";
        }
        break;

    case 'remove':
        if (!$email) {
            echo "Uso: whitelist.php remove <email>\n";
            exit(1);
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->rowCount() > 0) {
            echo "OK: $email eliminado.\n";
        } else {
            echo "WARN: $email no encontrado.\n";
        }
        break;

    case 'list':
        $rows = $pdo->query('SELECT email, name, is_admin, created_at FROM users ORDER BY is_admin DESC, created_at')->fetchAll();
        if (!$rows) {
            echo "Sin usuarios registrados.\n";
            break;
        }
        echo str_pad('EMAIL', 35) . str_pad('NOMBRE', 25) . str_pad('ADMIN', 7) . "CREADO\n";
        echo str_repeat('-', 82) . "\n";
        foreach ($rows as $row) {
            echo str_pad($row['email'], 35)
                . str_pad($row['name'] ?? '-', 25)
                . str_pad($row['is_admin'] ? 'sí' : 'no', 7)
                . $row['created_at'] . "\n";
        }
        break;

    case 'grant-admin':
        if (!$email) {
            echo "Uso: whitelist.php grant-admin <email>\n";
            exit(1);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO users (id, email, is_admin) VALUES (:id, :email, TRUE)
             ON CONFLICT (email) DO UPDATE SET is_admin = TRUE'
        );
        $stmt->execute(['id' => Uuid::uuid4()->toString(), 'email' => $email]);
        $existed = $pdo->prepare('SELECT name FROM users WHERE email = :email');
        $existed->execute(['email' => $email]);
        $user = $existed->fetch();
        if ($user && $user['name']) {
            echo "OK: $email ahora es admin.\n";
        } else {
            echo "OK: $email creado y marcado como admin (completará su perfil al primer login).\n";
        }
        break;

    case 'revoke-admin':
        if (!$email) {
            echo "Uso: whitelist.php revoke-admin <email>\n";
            exit(1);
        }
        $stmt = $pdo->prepare('UPDATE users SET is_admin = FALSE WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->rowCount() > 0) {
            echo "OK: $email ya no es admin.\n";
        } else {
            echo "WARN: $email no encontrado. Usá 'add' primero si querés registrarlo sin admin.\n";
        }
        break;

    default:
        echo "Uso:\n";
        echo "  php bin/whitelist.php add <email>          Agrega un usuario autorizado\n";
        echo "  php bin/whitelist.php remove <email>       Elimina un usuario\n";
        echo "  php bin/whitelist.php list                  Lista todos los usuarios\n";
        echo "  php bin/whitelist.php grant-admin <email>  Otorga privilegios de admin\n";
        echo "  php bin/whitelist.php revoke-admin <email> Revoca privilegios de admin\n";
        echo "\nEjemplo (Docker):\n";
        echo "  docker compose exec app php bin/whitelist.php add vos@gmail.com\n";
        echo "  docker compose exec app php bin/whitelist.php grant-admin vos@gmail.com\n";
        exit(1);
}
