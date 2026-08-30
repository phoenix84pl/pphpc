<?php

namespace Phoenix\Core\Console;

class DbPullData
{
    public static function run(string $baseDir): void
    {
        $remoteHost = $_ENV['REMOTE_SSH_HOST'] ?? '';
        $remotePath = $_ENV['REMOTE_PROJECT_PATH'] ?? '';

        if (empty($remoteHost) || empty($remotePath)) {
            echo "❌ Błąd: Brak zmiennych REMOTE_SSH_HOST lub REMOTE_PROJECT_PATH w pliku .env\n";
            echo "💡 Uzupełnij te zmienne w .env (np. REMOTE_SSH_HOST=pgs, REMOTE_PROJECT_PATH=/home/phoenix/www/pt)\n";
            exit(1);
        }

        $localDir = rtrim($baseDir, '/') . '/database';
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        echo "🚀 Rozpoczynam pobieranie bazy danych z serwera: {$remoteHost}\n";
        echo "--------------------------------------------------\n";

        // Pobierz najpierw schemat, potem dane
        self::pullFile($remoteHost, $remotePath, $localDir, 'schema.sql');
        echo "\n";
        self::pullFile($remoteHost, $remotePath, $localDir, 'data.sql');

        echo "✅ Pobieranie bazy danych (schema i data) zakończone!\n";
    }

    private static function pullFile(string $remoteHost, string $remotePath, string $localDir, string $fileName): void
    {
        $localFile = $localDir . '/' . $fileName;
        $remoteFile = rtrim($remotePath, '/') . '/database/' . $fileName;

        echo "📥 Sprawdzanie {$fileName} na serwerze...\n";

        // 1. Pobieramy rozmiar zdalnego pliku przez SSH, żeby znać 100%
        $sizeCmd = sprintf('ssh %s "stat -c%%s %s 2>/dev/null"', escapeshellarg($remoteHost), escapeshellarg($remoteFile));
        $totalBytes = (int) trim((string) shell_exec($sizeCmd));

        if ($totalBytes <= 0) {
            echo "⚠️  Plik {$fileName} nie istnieje na serwerze lub jest pusty. Pomijam.\n";
            return;
        }

        $totalMb = round($totalBytes / (1024 * 1024), 2);
        echo "📦 Pobieranie database/{$fileName} ({$totalMb} MB)...\n\n";

        $startTime = microtime(true);

        // 2. Otwieramy strumień SSH do czytania zawartości pliku
        $srcCmd = sprintf('ssh %s "cat %s"', escapeshellarg($remoteHost), escapeshellarg($remoteFile));
        $srcHandle = popen($srcCmd, 'r');
        $destHandle = fopen($localFile, 'w');

        if (!$srcHandle || !$destHandle) {
            echo "❌ Błąd otwarcia strumienia pobierania dla {$fileName}.\n";
            return;
        }

        $downloadedBytes = 0;
        $chunkSize = 1024 * 1024; // Pakiety po 1 MB

        while (!feof($srcHandle)) {
            $buffer = fread($srcHandle, $chunkSize);
            $bytesRead = strlen($buffer);
            
            if ($bytesRead > 0) {
                fwrite($destHandle, $buffer);
                $downloadedBytes += $bytesRead;

                // Obliczanie procentu i rysowanie paska
                $percent = min(100, round(($downloadedBytes / $totalBytes) * 100));
                $barLength = 30; // Szerokość paska w znakach
                $filledLength = (int) round(($barLength * $percent) / 100);
                
                $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
                
                $currentMb = round($downloadedBytes / (1024 * 1024), 2);
                
                // \r cofa kursor na początek linii w terminalu
                printf("\r⏳ [%s] %3d%%  (%s / %s MB)", $bar, $percent, $currentMb, $totalMb);
                flush();
            }
        }

        pclose($srcHandle);
        fclose($destHandle);

        echo "\n\n"; // Nowa linia po zakończeniu paska

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if (file_exists($localFile) && filesize($localFile) > 0) {
            $bytes = filesize($localFile);
            $mbSize = round($bytes / (1024 * 1024), 2);
            $speed = $duration > 0 ? round($mbSize / $duration, 2) : $mbSize;

            echo "✅ Pobrano {$fileName}:\n";
            echo "   Rozmiar: {$mbSize} MB | Czas: {$duration} s | Prędkość: {$speed} MB/s\n";
        } else {
            echo "❌ Błąd podczas pobierania pliku {$fileName}.\n";
        }
    }
}

Approve plan?
