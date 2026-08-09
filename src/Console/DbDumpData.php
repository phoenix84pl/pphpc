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

        $startTime = microtime(true);

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

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if ($returnVar === 0 && file_exists($outputFile)) {
            $bytes = filesize($outputFile);
            $mbSize = round($bytes / (1024 * 1024), 2);
            
            // Obliczanie prędkości zapisu (MB/s)
            $speed = $duration > 0 ? round($mbSize / $duration, 2) : $mbSize;

            echo "✅ Zrzut danych zakończony sukcesem!\n";
            echo "--------------------------------------------------\n";
            echo "📄 Plik wyjściowy : database/data.sql\n";
            echo "📊 Rozmiar pliku  : {$mbSize} MB ({$bytes} B)\n";
            echo "⏱️ Czas trwania   : {$duration} s\n";
            echo "⚡ Średnia prędkość: {$speed} MB/s\n";
            echo "--------------------------------------------------\n";
        } else {
            echo "❌ Błąd podczas wykonywania mysqldump:\n";
            echo implode("\n", $output) . "\n";
            exit(1);
        }
    }
}