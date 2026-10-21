<?php

/**
 * database.php
 *
 * Connexion PDO à la base active définie dans .env
 */

function loadEnv(string $file): void
{
    if (!file_exists($file)) {
        throw new Exception(
            "Fichier .env introuvable : " . $file
        );
    }

    $lines = file(
        $file,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($lines as $line) {

        $line = trim($line);

        // Ignorer les commentaires
        if (
            $line === '' ||
            str_starts_with($line, '#')
        ) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode(
            '=',
            $line,
            2
        );

        $name = trim($name);
        $value = trim($value);

        // Supprimer éventuellement les guillemets
        $value = trim(
            $value,
            "\"'"
        );

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}


/**
 * Charger .env
 */

loadEnv(
    __DIR__ . '/.env'
);


/**
 * Configuration
 */

$dbConnection = $_ENV['DB_CONNECTION'] ?? 'mysql';

$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';

$dbPort = $_ENV['DB_PORT'] ?? '3306';

$dbName = $_ENV['DB_DATABASE'] ?? 'analytics';

$dbUser = $_ENV['DB_USERNAME'] ?? 'root';

$dbPassword = $_ENV['DB_PASSWORD'] ?? '';


/**
 * Construction DSN
 */

switch ($dbConnection) {

    case 'mysql':

        $dsn =
            "mysql:host={$dbHost};" .
            "port={$dbPort};" .
            "dbname={$dbName};" .
            "charset=utf8mb4";

        break;


    case 'pgsql':

        $dsn =
            "pgsql:host={$dbHost};" .
            "port={$dbPort};" .
            "dbname={$dbName}";

        break;


    default:

        throw new Exception(
            "DB_CONNECTION non supportée : " .
            $dbConnection
        );
}


/**
 * Connexion PDO
 */

try {

    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false
        ]
    );

} catch (PDOException $e) {

    die(
        "Erreur de connexion à la base de données : " .
        htmlspecialchars($e->getMessage())
    );
}
