<?php

namespace Phoenix\Core\Console;

class DbDumpSchema
{
    public static function run(string $baseDir): void
    {
        global $db;

        if (!$db) {
            echo "❌ Błąd: Brak połączenia z bazą danych (\$db nie jest zainicjalizowane).\n";
            exit(1);
        }

        $host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user   = $_ENV['DB_USER'] ?? '';
        $pass   = $_ENV['DB_PASS'] ?? '';
        $dbname = $_ENV['DB_NAME'] ?? '';

        if (empty($dbname)) {
            echo "❌ Błąd: Brak nazwy bazy danych (DB_NAME) w .env.\n";
            exit(1);
        }

        // 1. Pobieramy listę samych widoków, aby wykluczyć je z pliku schema.sql
        $views = $db->query("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '$dbname' AND TABLE_TYPE = 'VIEW'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $ignoreViewsFlags = '';
        foreach ($views as $view) {
            $ignoreViewsFlags .= ' --ignore-table=' . escapeshellarg($dbname . '.' . $view);
        }

        $targetDir = rtrim($baseDir, '/') . '/database';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $outputFile = $targetDir . '/schema.sql';

        echo "🔄 Zrzucanie struktury samych tabel bazy danych (bez danych i widoków)...\n";

        // 2. mysqldump bez danych, bez komentarzy, bez widoków
        $command = sprintf(
            'mysqldump --no-data --skip-comments --skip-add-locks%s -h %s -u %s %s %s > %s 2>&1',
            $ignoreViewsFlags,
            escapeshellarg($host),
            escapeshellarg($user),
            !empty($pass) ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($dbname),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            echo "❌ Błąd podczas generowania schema.sql:\n";
            echo implode("\n", $output) . "\n";
            exit(1);
        }

        echo "✅ Pomyślnie wygenerowano plik: database/schema.sql (wyłącznie tabele)\n";
    }
}