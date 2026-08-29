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

        // --- GLOBALNE LOGOWANIE BŁĘDÓW PHP DO PLIKU ---
        $tmpDir = $baseDir . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        ini_set('log_errors', '1');
        ini_set('error_log', $tmpDir . '/php_error.log');
        error_reporting(E_ALL);

        // 3. Obsługa trybu APP_DEBUG z .env
        $isDebug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG')) == '1';

        if ($isDebug) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');

            // Rejestracja globalnego handlera w trybie DEBUG (pokazuje błąd na ekranie)
            set_exception_handler(function (\Throwable $e) {
                http_response_code(500);
                echo "<div style='background: #1e1e1e; color: #f8f8f2; padding: 20px; font-family: monospace; font-size: 14px; line-height: 1.5; border-left: 5px solid #ff5555; margin: 20px;'>";
                echo "<h2 style='color: #ff5555; margin-top: 0;'>[APP_DEBUG=1] Fatal Exception / Error</h2>";
                echo "<p><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><b>File:</b> " . htmlspecialchars($e->getFile()) . " (line " . $e->getLine() . ")</p>";
                echo "<p><b>Stack trace:</b></p>";
                echo "<pre style='background: #111; color: #50fa7b; padding: 12px; overflow: auto; border-radius: 4px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                echo "</div>";
                exit;
            });
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');

            // Rejestracja globalnego handlera na PRODUKCJĘ (cicho zapisuje w tle, użytkownik widzi bezpieczny komunikat)
            set_exception_handler(function (\Throwable $e) use ($baseDir) {
                http_response_code(500);
                $errorCode = 500;

                $errorViewApp = $baseDir . '/views/error.phtml';
                $errorViewCore = dirname(__DIR__) . '/views/error.phtml';

                if (file_exists($errorViewApp)) {
                    require $errorViewApp;
                } elseif (file_exists($errorViewCore)) {
                    require $errorViewCore;
                } else {
                    echo "<div style='background: #fdf2f2; color: #9b1c1c; padding: 20px; font-family: sans-serif; text-align: center; margin: 40px; border: 1px solid #f8b4b4; border-radius: 6px;'>";
                    echo "<h2>Application Error</h2>";
                    echo "<p>Sorry, something went wrong. The administrator has been notified.</p>";
                    echo "</div>";
                }
                exit;
            });
        }

        // 4. Domyślna instancja bazy
        if (!isset($GLOBALS['db']) && class_exists('\Phoenix\Core\Database')) {
            try {
                $GLOBALS['db'] = new Database();
            } catch (\Throwable $e) {
                $GLOBALS['db'] = null;
            }
        }
    }
}