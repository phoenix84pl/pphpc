<?php

namespace Phoenix\Core\Console;

use PDO;
use Exception;

class DbImportData
{
    public static function run(string $baseDir): void
    {
        // 1. Zabezpieczenie środowiska
        $appEnv = strtolower($_ENV['APP_ENV'] ?? 'local');
        if (in_array($appEnv, ['prod', 'production', 'live'])) {
            echo "❌ BŁĄD BEZPIECZEŃSTWA!\n";
            echo "🛑 Wygląda na to, że jesteś w środowisku PRODUKCYJNYM (APP_ENV={$appEnv}).\n";
            echo "⚠️ Importowanie danych zostało zablokowane, aby uniknąć nadpisania bazy danych!\n";
            exit(1);
        }

        $localFile = rtrim($baseDir, '/') . '/database/data.sql';

        if (!file_exists($localFile) || filesize($localFile) === 0) {
            echo "❌ Błąd: Brak pliku database/data.sql lub plik jest pusty.\n";
            echo "💡 Najpierw uruchom komendę vendor/bin/pphpc db:pull\n";
            exit(1);
        }

        $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $dbUser = $_ENV['DB_USER'] ?? '';
        $dbPass = $_ENV['DB_PASS'] ?? '';

        if (empty($dbName) || empty($dbUser)) {
            echo "❌ Błąd: Brak konfiguracji bazy danych (DB_NAME lub DB_USER) w pliku .env\n";
            exit(1);
        }

        $totalBytes = filesize($localFile);
        $totalMb = round($totalBytes / (1024 * 1024), 2);

        // 2. Analiza stanu lokalnej bazy danych
        echo "🛡️  Środowisko lokalne potwierdzone (APP_ENV={$appEnv})\n";
        echo "🔍 Analizowanie stanu lokalnej bazy danych '{$dbName}'...\n";

        $tableCount = 0;
        $totalRows = 0;

        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($tables);

            if ($tableCount > 0) {
                foreach ($tables as $table) {
                    $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                    $totalRows += (int) $countStmt->fetchColumn();
                }
            }
        } catch (Exception $e) {
            echo "⚠️ Nie udało się połączyć/zanalizować bazy przed importem: " . $e->getMessage() . "\n";
            exit(1);
        }

        echo "--------------------------------------------------\n";
        
        if ($tableCount === 0) {
            echo "❌ BŁĄD STRUKTURY BAZY!\n";
            echo "🛑 Lokalna baza '{$dbName}' jest całkowicie pusta (0 tabel).\n";
            echo "💡 Plik data.sql zawiera jedynie dane. Najpierw utwórz strukturę tabel (schema.sql)!\n";
            exit(1);
        } elseif ($totalRows === 0) {
            echo "✅ Stan lokalnej bazy : IDEALNY (Struktura istnieje, 0 rekordów)\n";
            echo "📊 Wykryte tabele     : {$tableCount}\n";
        } else {
            echo "⚠️ Stan lokalnej bazy : ZAWIERA JUŻ DANE!\n";
            echo "📊 Wykryte tabele     : {$tableCount}\n";
            echo "🔢 Łączna liczba wierszy: {$totalRows} (zostaną zastąpione/dopisane)\n";
        }
        
        echo "📄 Plik do wczytania  : database/data.sql ({$totalMb} MB)\n";
        echo "--------------------------------------------------\n";

        // 3. Pytanie o potwierdzenie operacji z wielkim 'Y'
        echo "❓ Czy na pewno chcesz zaimportować dane do lokalnej bazy? [Y/n]: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $confirmation = trim((string)$line);

        if ($confirmation !== 'Y' && $confirmation !== 'YES') {
            echo "❌ Operacja anulowana przez użytkownika.\n";
            exit(0);
        }

        echo "\n🚀 Rozpoczynam import danych...\n\n";

        $startTime = microtime(true);

        $passParam = !empty($dbPass) ? sprintf('-p%s', escapeshellarg($dbPass)) : '';
        $mysqlCmd = sprintf(
            'mysql -h %s -u %s %s %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName)
        );

        $srcHandle = fopen($localFile, 'r');
        $destHandle = popen($mysqlCmd, 'w');

        if (!$srcHandle || !$destHandle) {
            echo "❌ Błąd otwarcia strumienia importu bazy danych.\n";
            exit(1);
        }

        $importedBytes = 0;
        $chunkSize = 1024 * 1024; // Pakiety po 1 MB

        while (!feof($srcHandle)) {
            $buffer = fread($srcHandle, $chunkSize);
            $bytesRead = strlen($buffer);

            if ($bytesRead > 0) {
                fwrite($destHandle, $buffer);
                $importedBytes += $bytesRead;

                $percent = min(100, round(($importedBytes / $totalBytes) * 100));
                $barLength = 30;
                $filledLength = (int) round(($barLength * $percent) / 100);

                $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
                $currentMb = round($importedBytes / (1024 * 1024), 1);

                printf("\r⚙️  [%s] %3d%%  (%s / %s MB)", $bar, $percent, $currentMb, $totalMb);
                flush();
            }
        }

        fclose($srcHandle);
        $returnVar = pclose($destHandle);

        echo "\n\n";

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if ($returnVar === 0) {
            $speed = $duration > 0 ? round($totalMb / $duration, 2) : $totalMb;

            echo "✅ Import zakończony sukcesem!\n";
            echo "--------------------------------------------------\n";
            echo "🗄️ Baza docelowa  : {$dbName}\n";
            echo "📊 Wczytano dane  : {$totalMb} MB\n";
            echo "⏱️ Czas trwania   : {$duration} s\n";
            echo "⚡ Prędkość       : {$speed} MB/s\n";
            echo "--------------------------------------------------\n";
        } else {
            echo "❌ Błąd podczas wykonywania zapytania w bazie MariaDB/MySQL.\n";
            exit(1);
        }
    }
}