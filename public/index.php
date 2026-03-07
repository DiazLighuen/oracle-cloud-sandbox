<?php
$host = 'db'; 
$db   = getenv('POSTGRES_DB');   
$user = getenv('POSTGRES_USER');
$pass = getenv('POSTGRES_PASSWORD');

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$db;";
    if (!class_exists('PDO')) {
        echo "<h1>🚀 Oracle Cloud Sandbox</h1>";
        echo "<p style=\"color:crimson\">Error: La extensión PDO no está disponible en esta instalación de PHP.</p>";
        echo "<p>PHP Version: " . phpversion() . "</p>";
        exit(1);
    }

    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "<h1>🚀 Oracle Cloud Sandbox</h1>";
    echo "<p>Status: <strong>Connected to PostgreSQL successfully!</strong></p>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage();
}