<?php

namespace Phoenix\Core;

use Dotenv\Dotenv;

class Bootstrap
{
    /**
     * Inicjalizuje środowisko aplikacji (Autoload, .env, Baza danych)
     */
    public static function init(string $baseDir): void
    {
        // 1. Ładowanie zmiennych środowiskowych z .env
        if (file_exists($baseDir . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($baseDir);
                $dotenv->load();
            } catch (\Throwable $e) {
                // Gdy brak .env lub wystąpił błąd, aplikacja idzie dalej
            }
        }

        // 2. Inicjalizacja połączenia z bazą danych ($db)
        if (!isset($GLOBALS['db']) && class_exists('\Phoenix\Core\Database')) {
            try {
                if (isset($_ENV['DB_HOST']) && $_ENV['DB_HOST'] !== '') {
                    $GLOBALS['db'] = new Database(
                        $_ENV['DB_HOST'],
                        $_ENV['DB_USER'],
                        $_ENV['DB_PASS'],
                        $_ENV['DB_NAME']
                    );
                } else {
                    $GLOBALS['db'] = new Database();
                }
            } catch (\Throwable $e) {
                $GLOBALS['db'] = $e;
            }
        }

        // Tworzymy referencję dla zmiennej globalnej $db
        $GLOBALS['db'] = $GLOBALS['db'] ?? null;
    }
}