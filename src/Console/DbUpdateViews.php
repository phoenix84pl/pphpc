<?php

namespace Phoenix\Core\Console;

class DbUpdateViews
{
    public static function run(string $baseDir): void
    {
        global $db;

        if (!$db) {
            echo "❌ Błąd: Brak połączenia z bazą danych (\$db nie jest zainicjalizowane).\n";
            exit(1);
        }

        $viewsDir = rtrim($baseDir, '/') . '/database/views';
        $files = glob($viewsDir . '/*.sql');

        if (empty($files)) {
            echo "ℹ️ Brak plików .sql w katalogu {$viewsDir}\n";
            exit(0);
        }

        sort($files);

        echo "🔄 Aktualizacja widoków bazy danych (SQL)...\n";
        echo "--------------------------------------------------\n";

        $successCount = 0;

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $sql = file_get_contents($filePath);

            if (empty(trim($sql))) {
                echo "⚠️  [POMINIĘTO] {$fileName} (pusty plik)\n";
                continue;
            }

            try {
                $db->exec($sql);
                echo "✅ [OK] {$fileName}\n";
                $successCount++;
            } catch (\PDOException $e) {
                echo "❌ [BŁĄD] {$fileName}\n";
                echo "   Komunikat: " . $e->getMessage() . "\n";
                echo "--------------------------------------------------\n";
                echo "⛔ Wykonywanie przerwane ze względu na błąd w strukturze SQL.\n";
                exit(1);
            }
        }

        echo "--------------------------------------------------\n";
        echo "🚀 Gotowe! Pomyślnie wdrożono {$successCount} widoków bazy danych.\n";
    }
}