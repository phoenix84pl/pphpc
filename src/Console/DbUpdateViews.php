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

        if (!is_dir($viewsDir)) {
            echo "⚠️ Katalog z widokami nie istnieje: {$viewsDir}\n";
            return;
        }

        $files = glob($viewsDir . '/*.sql');

        if (empty($files)) {
            echo "ℹ️ Brak plików .sql w katalogu database/views/\n";
            return;
        }

        echo "🔄 Aktualizacja widoków bazy danych (SQL)...\n";
        echo "--------------------------------------------------\n";

        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            $sql = file_get_contents($file);

            if (empty(trim($sql))) {
                continue;
            }

            try {
                $db->exec($sql);
                echo "  ✅ Pomyślnie wdrożono widok: {$filename}\n";
                $count++;
            } catch (\PDOException $e) {
                echo "  ❌ Błąd w pliku {$filename}:\n";
                echo "     " . $e->getMessage() . "\n";
            }
        }

        echo "--------------------------------------------------\n";
        echo "✅ Zakończono! Wdrożono widoków: {$count}\n";
    }
}