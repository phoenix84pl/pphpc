<?php

namespace Phoenix\Core\Console;

use PDO;
use Exception;

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

        // 1. Szacowanie rozmiaru bazy dla paska postępu
        $estimatedBytes = 0;
        try {
            $dsn = "mysql:host={$dbHost};dbname=information_schema;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $pdo->prepare("SELECT SUM(DATA_LENGTH) FROM TABLES WHERE TABLE_SCHEMA = ?");
            $stmt->execute([$dbName]);
            $estimatedBytes = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // Ignorujemy błąd, jeśli się nie uda oszacować, będziemy pokazywać tylko zrzucone MB
        }

        $startTime = microtime(true);

        $passParam = !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '';
        // Zauważ, że usuwamy > i przekierowanie, bo sami to będziemy czytać
        $cmd = sprintf(
            'mysqldump --no-create-info --single-transaction --skip-triggers -h %s -u %s %s %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName)
        );

        $srcHandle = popen($cmd, 'r');
        $destHandle = fopen($outputFile, 'w');

        if (!$srcHandle || !$destHandle) {
            echo "❌ Błąd uruchomienia procesu mysqldump.\n";
            exit(1);
        }

        $dumpedBytes = 0;
        $chunkSize = 1024 * 1024; // Pakiety po 1 MB

        while (!feof($srcHandle)) {
            $buffer = fread($srcHandle, $chunkSize);
            $bytesRead = strlen($buffer);

            if ($bytesRead > 0) {
                fwrite($destHandle, $buffer);
                $dumpedBytes += $bytesRead;

                $currentMb = round($dumpedBytes / (1024 * 1024), 1);
                
                if ($estimatedBytes > 0) {
                    $percent = min(99, round(($dumpedBytes / $estimatedBytes) * 100)); // Trzymamy do 99%, 100% będzie na koniec
                    $barLength = 30;
                    $filledLength = (int) round(($barLength * $percent) / 100);
                    $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
                    
                    printf("\r⚙️  [%s] %3d%%  (%s MB zrzucono)", $bar, $percent, $currentMb);
                } else {
                    printf("\r⚙️  Zrzucono: %s MB...", $currentMb);
                }
                flush();
            }
        }

        $returnVar = pclose($srcHandle);
        fclose($destHandle);

        if ($estimatedBytes > 0) {
            // Wymuszenie wizualnego 100% po zakończeniu
            $bar = str_repeat('█', 30);
            $currentMb = round($dumpedBytes / (1024 * 1024), 1);
            printf("\r⚙️  [%s] 100%%  (%s MB zrzucono)", $bar, $currentMb);
        }

        echo "\n\n";

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if ($returnVar === 0 && file_exists($outputFile)) {
            $bytes = filesize($outputFile);
            $mbSize = round($bytes / (1024 * 1024), 2);
            $speed = $duration > 0 ? round($mbSize / $duration, 2) : $mbSize;

            echo "✅ Zrzut danych zakończony sukcesem!\n";
            echo "--------------------------------------------------\n";
            echo "📄 Plik wyjściowy : database/data.sql\n";
            echo "📊 Rozmiar pliku  : {$mbSize} MB ({$bytes} B)\n";
            echo "⏱️ Czas trwania   : {$duration} s\n";
            echo "⚡ Średnia prędkość: {$speed} MB/s\n";
            echo "--------------------------------------------------\n";
        } else {
            echo "❌ Błąd podczas wykonywania mysqldump (kod błędu: {$returnVar}).\n";
            exit(1);
        }
    }
}