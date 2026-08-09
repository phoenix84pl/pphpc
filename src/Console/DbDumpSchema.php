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

        $targetDir = rtrim($baseDir, '/') . '/database';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $outputFile = $targetDir . '/schema.sql';

        echo "🔄 Zrzucanie struktury bazy danych (bez danych)...\n";

        // mysqldump --no-data wyciąga samą strukturę tabel i widoków
        $command = sprintf(
            'mysqldump --no-data --skip-comments --skip-add-locks -h %s -u %s %s %s > %s 2>&1',
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

        echo "✅ Pomyślnie wygenerowano plik: database/schema.sql\n";
    }
}