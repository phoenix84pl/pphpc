<?php

namespace Phoenix\Core;

use Dotenv\Dotenv;

class Bootstrap
{
    public static function init(string $baseDir): void
    {
        // 1. Inicjalizacja sesji
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 2. Ładowanie zmiennych środowiskowych (.env)
        if (file_exists($baseDir . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($baseDir);
                $dotenv->load();
            } catch (\Throwable $e) {
                // Gdy brak .env, aplikacja idzie dalej
            }
        }

        // 3. Domyślna instancja bazy
        if (!isset($GLOBALS['db']) && class_exists('\Phoenix\Core\Database')) {
            try {
                $GLOBALS['db'] = new Database();
            } catch (\Throwable $e) {
                $GLOBALS['db'] = null;
            }
        }
    }
}