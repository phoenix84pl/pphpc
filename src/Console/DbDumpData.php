<?php

namespace Phoenix\Core\Console;

class DbDumpData
{
    public static function run(string $baseDir): void
    {
        $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $dbUser = $_ENV['DB_USER'] ?? '';
        $dbPass = $_ENV['DB_PASS'] ?? '';

        if (empty($dbName) || empty($dbUser)) {
            echo "❌ Błąd: Brak wymaganych zmiennych DB_NAME / DB_USER w pliku .env\n";
            exit(1);
        }

        $outputDir = rtrim($baseDir, '/') . '/database';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputFile = $outputDir . '/data.sql';

        echo "📦 Tworzenie zrzutu samych danych z bazy '{$dbName}'...\n";

        $cmd = sprintf(
            'mysqldump --no-create-info --single-transaction --skip-triggers -h %s -u %s %s %s > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName),
            escapeshellarg($outputFile)
        );

        $returnVar = 0;
        $output = [];
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && file_exists($outputFile)) {
            $filesize = round(filesize($outputFile) / 1024, 2);
            echo "✅ Zrzut danych zakończony sukcesem!\n";
            echo "📄 Plik wyjściowy: database/data.sql ({$filesize} KB)\n";
        } else {
            echo "❌ Błąd podczas wykonywania mysqldump:\n";
            echo implode("\n", $output) . "\n";
            exit(1);
        }
    }
}