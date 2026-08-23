<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = self::requiredEnv('DB_HOST');
            $port = self::requiredEnv('DB_PORT');
            $dbname = self::requiredEnv('DB_NAME');
            $user = self::requiredEnv('DB_USER');
            $password = self::requiredEnv('DB_PASSWORD');

            if (ctype_digit($port) === false) {
                throw new RuntimeException('Configuração de banco inválida');
            }

            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

            try {
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Falha na conexão com o banco', previous: $e);
            }
        }

        return self::$instance;
    }

    private static function requiredEnv(string $key): string
    {
        $value = $_ENV[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("Configuração ausente: {$key}");
        }

        return trim($value);
    }
}
